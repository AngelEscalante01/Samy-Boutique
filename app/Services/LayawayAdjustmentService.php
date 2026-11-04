<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Layaway;
use App\Models\LayawayAdjustment;
use App\Models\LayawayItem;
use App\Models\LayawayRefund;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LayawayAdjustmentService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function preview(Layaway $layaway, array $payload, User $user): array
    {
        $layaway->loadMissing('items');
        $this->assertEditable($layaway);
        if ((int) $payload['version'] !== (int) $layaway->version) {
            throw ValidationException::withMessages(['version' => 'El apartado cambió. Actualiza la página antes de confirmar.']);
        }
        $current = $layaway->items->keyBy('product_variant_id');
        if ($current->has(null)) {
            throw ValidationException::withMessages(['items' => 'Hay una partida antigua sin variante. Requiere revisión manual.']);
        }
        $ids = collect($payload['items'])->pluck('variant_id')->map(fn ($id) => (int) $id);
        if ($ids->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'No repitas variantes en el mismo apartado.']);
        }
        $variants = ProductVariant::query()->whereIn('id', $ids)->with(['product', 'size', 'color'])->get()->keyBy('id');
        if ($variants->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'Una variante ya no existe.']);
        }
        $lines = [];
        $changes = [];
        $grossCents = 0;
        $totalUnits = 0;
        foreach ($payload['items'] as $item) {
            $id = (int) $item['variant_id'];
            $qty = (int) $item['qty'];
            $variant = $variants[$id];
            $existing = $current->get($id);
            $oldQty = $existing ? (int) ($existing->qty ?? $existing->quantity) : 0;
            if ((! $variant->active && $qty > $oldQty) || (int) $variant->stock < $qty - $oldQty) {
                throw ValidationException::withMessages(['items' => "Stock insuficiente para {$variant->sku}."]);
            }
            $priceCents = $existing ? (int) round((float) $existing->unit_price * 100)
                : (int) round($this->inventory->effectiveSalePrice($variant) * 100);
            $grossCents += $priceCents * $qty;
            $totalUnits += $qty;
            $lines[] = [
                'variant_id' => $id, 'product_id' => $variant->product_id,
                'sku' => $existing?->sku ?? $variant->sku,
                'name' => $existing?->name ?? $variant->product->name,
                'qty' => $qty, 'unit_price' => number_format($priceCents / 100, 2, '.', ''),
                'line_total' => number_format($priceCents * $qty / 100, 2, '.', ''),
            ];
            if ($qty !== $oldQty) {
                $changes[] = [
                    'variant_id' => $id, 'sku' => $existing?->sku ?? $variant->sku,
                    'name' => $existing?->name ?? $variant->product->name,
                    'size' => $variant->size?->name, 'color' => $variant->color?->name,
                    'before_qty' => $oldQty, 'after_qty' => $qty,
                    'unit_price' => number_format($priceCents / 100, 2, '.', ''),
                ];
            }
        }
        foreach ($current as $id => $item) {
            if ($ids->contains((int) $id)) continue;
            $changes[] = [
                'variant_id' => (int) $id, 'sku' => $item->sku, 'name' => $item->name,
                'size' => $item->variant?->size?->name, 'color' => $item->variant?->color?->name,
                'before_qty' => (int) ($item->qty ?? $item->quantity), 'after_qty' => 0,
                'unit_price' => (string) $item->unit_price,
            ];
        }
        $type = $payload['global_discount_type'] ?? $layaway->global_discount_type;
        $value = (float) ($payload['global_discount_value'] ?? $layaway->global_discount_value ?? 0);
        $discountChanged = (string) ($type ?? '') !== (string) ($layaway->global_discount_type ?? '')
            || bccomp(number_format($value, 2, '.', ''), number_format((float) ($layaway->global_discount_value ?? 0), 2, '.', ''), 2) !== 0;
        if ($discountChanged) {
            ManualDiscountPermission::assertAllowed($user, $type, $value);
        }
        if ($totalUnits < 2) {
            $type = null;
            $value = 0;
        }
        if ($value > 0 && ! in_array($type, ['percent', 'amount'], true)) {
            throw ValidationException::withMessages(['global_discount_type' => 'Selecciona un tipo de descuento válido.']);
        }
        $discountCents = $type === 'percent'
            ? (int) round($grossCents * min(100, $value) / 100)
            : min($grossCents, (int) round($value * 100));
        $loyaltyCents = min($grossCents - $discountCents, (int) round((float) ($layaway->loyalty_discount_total ?? 0) * 100));
        $totalCents = $grossCents - $discountCents - $loyaltyCents;
        $appliedCents = (int) round((float) $layaway->applied_total * 100);

        return [
            'version' => (int) $layaway->version,
            'customer_id' => $payload['customer_id'] ?? null,
            'items' => $lines,
            'changes' => $changes,
            'subtotal' => number_format($grossCents / 100, 2, '.', ''),
            'global_discount_type' => $discountCents > 0 ? $type : null,
            'global_discount_value' => $discountCents > 0 ? number_format($value, 2, '.', '') : null,
            'manual_discount_total' => number_format($discountCents / 100, 2, '.', ''),
            'loyalty_discount_total' => number_format($loyaltyCents / 100, 2, '.', ''),
            'total' => number_format($totalCents / 100, 2, '.', ''),
            'applied_total' => number_format($appliedCents / 100, 2, '.', ''),
            'balance' => number_format(max(0, $totalCents - $appliedCents) / 100, 2, '.', ''),
            'excess' => number_format(max(0, $appliedCents - $totalCents) / 100, 2, '.', ''),
        ];
    }

    public function update(Layaway $layaway, array $payload, User $user): Layaway
    {
        return DB::transaction(function () use ($layaway, $payload, $user) {
            $locked = Layaway::query()->lockForUpdate()->with('items')->findOrFail($layaway->id);
            $before = $this->snapshot($locked);
            $preview = $this->preview($locked, $payload, $user);
            if (isset($payload['customer_id']) && $payload['customer_id'] !== null) {
                Customer::query()->findOrFail($payload['customer_id']);
            }
            $newItems = collect($preview['items'])->keyBy('variant_id');
            $oldItems = $locked->items->keyBy('product_variant_id');
            $ids = $oldItems->keys()->merge($newItems->keys())->unique()->sort()->values();
            $variants = ProductVariant::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $preview = $this->preview($locked, $payload, $user);
            $newItems = collect($preview['items'])->keyBy('variant_id');
            foreach ($ids as $id) {
                $variant = $variants->get((int) $id);
                $oldQty = (int) ($oldItems->get($id)?->qty ?? $oldItems->get($id)?->quantity ?? 0);
                $newQty = (int) ($newItems->get($id)['qty'] ?? 0);
                if (! $variant) throw ValidationException::withMessages(['items' => 'Una variante ya no existe.']);
                if ($newQty > $oldQty) {
                    if (! $variant->active || (int) $variant->stock < $newQty - $oldQty) {
                        throw ValidationException::withMessages(['items' => "Stock insuficiente para {$variant->sku}."]);
                    }
                    $this->inventory->decrementVariantStock($variant, $newQty - $oldQty);
                } elseif ($newQty < $oldQty) {
                    $this->inventory->incrementVariantStock($variant, $oldQty - $newQty);
                }
            }
            foreach ($oldItems as $id => $item) {
                if (! $newItems->has($id)) $item->delete();
            }
            foreach ($newItems as $id => $line) {
                $existing = $oldItems->get($id);
                if ($existing) {
                    $existing->update(['quantity' => $line['qty'], 'qty' => $line['qty']]);
                } else {
                    LayawayItem::create([
                        'layaway_id' => $locked->id, 'product_id' => $line['product_id'],
                        'product_variant_id' => $id, 'quantity' => $line['qty'], 'qty' => $line['qty'],
                        'sku' => $line['sku'], 'name' => $line['name'], 'unit_price' => $line['unit_price'],
                    ]);
                }
            }
            $locked->update([
                'customer_id' => $payload['customer_id'] ?? null,
                'subtotal' => $preview['subtotal'],
                'global_discount_type' => $preview['global_discount_type'],
                'global_discount_value' => $preview['global_discount_value'],
                'manual_discount_total' => $preview['manual_discount_total'],
                'loyalty_discount_total' => $preview['loyalty_discount_total'],
                'version' => $locked->version + 1,
            ]);
            if ($locked->status === 'liquidated') {
                $this->syncUndeliveredSale($locked, $preview);
            }
            $this->inventory->updateManySoldOutAt($variants->pluck('product_id')->map(fn ($id) => (int) $id));
            $locked->load('items');
            LayawayAdjustment::create([
                'layaway_id' => $locked->id, 'created_by' => $user->id,
                'version_before' => $before['version'], 'version_after' => $locked->version,
                'before_snapshot' => $before, 'after_snapshot' => $this->snapshot($locked),
                'reason' => $payload['reason'],
            ]);

            return $locked->fresh(['items', 'payments', 'refunds', 'customer']);
        }, 3);
    }

    public function refund(Layaway $layaway, array $payload, User $user): LayawayRefund
    {
        return DB::transaction(function () use ($layaway, $payload, $user) {
            $locked = Layaway::query()->lockForUpdate()->findOrFail($layaway->id);
            $operationKey = $payload['operation_key'] ?? null;
            if ($operationKey !== null) {
                $previous = LayawayRefund::query()
                    ->where('layaway_id', $locked->id)
                    ->where('operation_key', $operationKey)
                    ->first();
                if ($previous) {
                    $same = (int) round((float) $previous->amount * 100) === (int) round((float) $payload['amount'] * 100)
                        && $previous->method === $payload['method']
                        && (string) ($previous->reference ?? '') === (string) ($payload['reference'] ?? '')
                        && $previous->reason === $payload['reason']
                        && (int) $previous->created_by === (int) $user->id;
                    if (! $same) {
                        throw ValidationException::withMessages([
                            'operation_key' => 'Este intento de devolución ya corresponde a otros datos. Revisa el historial antes de continuar.',
                        ]);
                    }

                    return $previous;
                }
            }
            if ($locked->status === 'liquidated' && ($locked->delivered_at || ! $locked->delivery_tracking_enabled || $locked->sale?->status !== 'completed')) {
                throw ValidationException::withMessages(['layaway' => 'El apartado ya fue entregado.']);
            }
            $amountCents = (int) round((float) $payload['amount'] * 100);
            if ($amountCents <= 0 || $amountCents > (int) round((float) $locked->excess * 100)) {
                throw ValidationException::withMessages(['amount' => 'La devolución supera el excedente disponible.']);
            }
            $refund = LayawayRefund::create([
                'layaway_id' => $locked->id, 'created_by' => $user->id,
                'operation_key' => $operationKey,
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'method' => $payload['method'], 'reference' => $payload['reference'] ?? null,
                'reason' => $payload['reason'], 'status' => 'confirmed', 'refunded_at' => now(),
            ]);
            $locked->update(['refunded_total' => bcadd((string) $locked->refunded_total, (string) $refund->amount, 2)]);
            if ($locked->status === 'liquidated' && $locked->sale_id) {
                SalePayment::create([
                    'sale_id' => $locked->sale_id,
                    'method' => $refund->method,
                    'amount' => bcsub('0.00', (string) $refund->amount, 2),
                    'reference' => 'Devolución AP-'.$locked->id.($refund->reference ? ' '.$refund->reference : ''),
                ]);
            }

            return $refund;
        }, 3);
    }

    private function assertEditable(Layaway $layaway): void
    {
        if ($layaway->status !== 'open' && ! ($layaway->status === 'liquidated' && $layaway->delivery_tracking_enabled && ! $layaway->delivered_at && $layaway->sale?->status === 'completed')) {
            throw ValidationException::withMessages(['layaway' => 'Solo se puede editar un apartado abierto o liquidado pendiente de entrega.']);
        }
    }

    private function syncUndeliveredSale(Layaway $layaway, array $preview): void
    {
        $sale = $layaway->sale()->lockForUpdate()->firstOrFail();
        if ($sale->status !== 'completed') {
            throw ValidationException::withMessages(['layaway' => 'La venta vinculada ya no permite cambios.']);
        }
        $sale->update([
            'customer_id' => $layaway->customer_id,
            'subtotal' => $preview['subtotal'],
            'discount_total' => bcadd($preview['manual_discount_total'], $preview['loyalty_discount_total'], 2),
            'loyalty_discount_total' => $preview['loyalty_discount_total'],
            'total' => $preview['total'],
            'global_discount_type' => $preview['global_discount_type'],
            'global_discount_value' => $preview['global_discount_value'],
        ]);
        $oldLines = $sale->items()->get()->keyBy('product_variant_id');
        $newLines = collect($preview['items'])->keyBy('variant_id');
        foreach ($oldLines as $variantId => $line) {
            if (! $newLines->has($variantId)) $line->delete();
        }
        foreach ($newLines as $variantId => $line) {
            $values = [
                'product_id' => $line['product_id'],
                'product_variant_id' => $variantId,
                'quantity' => $line['qty'], 'qty' => $line['qty'],
                'sku' => $line['sku'], 'name' => $line['name'],
                'unit_price' => $line['unit_price'], 'line_total' => $line['line_total'],
                'final_price' => $line['line_total'],
            ];
            if ($oldLines->has($variantId)) {
                $oldLines[$variantId]->update($values);
            } else {
                SaleItem::create(array_merge($values, [
                    'sale_id' => $sale->id, 'discount_type' => null, 'discount_value' => null,
                    'discount_amount' => '0.00', 'discount' => '0.00',
                ]));
            }
        }
    }

    private function snapshot(Layaway $layaway): array
    {
        return [
            'version' => (int) $layaway->version,
            'customer_id' => $layaway->customer_id,
            'subtotal' => $layaway->subtotal,
            'manual_discount_total' => $layaway->manual_discount_total,
            'loyalty_discount_total' => $layaway->loyalty_discount_total,
            'total' => $layaway->total,
            'paid_total' => $layaway->paid_total,
            'refunded_total' => $layaway->refunded_total,
            'items' => $layaway->items->map(fn ($item) => [
                'id' => $item->id, 'variant_id' => $item->product_variant_id,
                'qty' => $item->qty ?? $item->quantity, 'unit_price' => $item->unit_price,
            ])->all(),
        ];
    }
}
