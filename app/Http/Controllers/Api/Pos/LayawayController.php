<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\AddApiLayawayPaymentRequest;
use App\Http\Requests\StoreApiLayawayRequest;
use App\Models\Layaway;
use App\Services\LayawayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LayawayController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $validated = $request->validate([
            'status' => ['nullable', 'in:open,liquidated,cancelled'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = (string) ($validated['status'] ?? 'open');
        $q = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $layaways = Layaway::query()
            ->with([
                'customer:id,name,phone,email',
                'creator:id,name',
                'payments:id,layaway_id,created_by,method,amount,reference,paid_at,created_at,observacion',
            ])
            ->when(in_array($status, ['open', 'liquidated', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($subQuery) use ($like) {
                    $subQuery
                        ->where('id', 'like', $like)
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', $like)->orWhere('phone', 'like', $like));
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $layaways->getCollection()->map(fn (Layaway $layaway) => $this->serializeLayawaySummary($layaway))->values()->all();

        return $this->paginatedResponse($layaways, $items, 'Apartados obtenidos correctamente.');
    }

    public function show(Layaway $layaway, Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $layaway->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'items:id,layaway_id,product_id,product_variant_id,qty,quantity,sku,name,unit_price',
            'items.variant:id,size_id,color_id,sku,stock',
            'items.variant.size:id,name',
            'items.variant.color:id,name,hex',
            'items.product:id,name,sku',
            'payments:id,layaway_id,created_by,method,amount,reference,paid_at,created_at,observacion',
            'payments.creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse($this->serializeLayawayDetail($layaway), 'Apartado obtenido correctamente.');
    }

    public function store(StoreApiLayawayRequest $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $validated = $request->validated();

        $payload = [
            'customer_id' => $validated['customer_id'] ?? null,
            'items' => $validated['items'],
            'payments' => $validated['payments'] ?? [],
            'vigencia_dias' => $validated['vigencia_dias'] ?? 30,
            'observaciones' => $validated['observaciones'] ?? null,
        ];

        if (! empty($validated['fecha_vencimiento'])) {
            $today = now()->startOfDay();
            $dueDate = Carbon::parse($validated['fecha_vencimiento'])->startOfDay();
            $payload['vigencia_dias'] = max(1, (int) $today->diffInDays($dueDate));
        }

        $layaway = $service->create($payload, $request->user());

        if (! empty($validated['fecha_vencimiento'])) {
            $today = now()->startOfDay();
            $dueDate = Carbon::parse($validated['fecha_vencimiento'])->startOfDay();
            $layaway->update([
                'fecha_vencimiento' => $dueDate->toDateString(),
                'vigencia_dias' => max(1, (int) $today->diffInDays($dueDate)),
            ]);
        }

        $layaway->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'items:id,layaway_id,product_id,product_variant_id,qty,quantity,sku,name,unit_price',
            'items.variant:id,size_id,color_id,sku,stock',
            'items.variant.size:id,name',
            'items.variant.color:id,name,hex',
            'items.product:id,name,sku',
            'payments:id,layaway_id,created_by,method,amount,reference,paid_at,created_at,observacion',
            'payments.creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse($this->serializeLayawayDetail($layaway), 'Apartado creado correctamente.', 201);
    }

    public function addPayment(Layaway $layaway, AddApiLayawayPaymentRequest $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $validated = $request->validated();

        $payment = $service->addPayment($layaway, [
            'method' => $validated['method'],
            'amount' => $validated['amount'],
            'reference' => $validated['reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now(),
            'observacion' => $validated['observacion'] ?? null,
        ], $request->user());

        $layaway->refresh();

        $liquidated = false;
        $saleId = null;

        if ((bool) ($validated['auto_liquidate'] ?? true) && $layaway->status === 'open' && (float) $layaway->balance <= 0.000001) {
            $sale = $service->liquidate($layaway, ['payments' => []], $request->user());
            $liquidated = true;
            $saleId = (int) $sale->id;
            $layaway->refresh();
        }

        $layaway->load([
            'customer:id,name,phone,email',
            'creator:id,name',
            'items:id,layaway_id,product_id,product_variant_id,qty,quantity,sku,name,unit_price',
            'items.variant:id,size_id,color_id,sku,stock',
            'items.variant.size:id,name',
            'items.variant.color:id,name,hex',
            'items.product:id,name,sku',
            'payments:id,layaway_id,created_by,method,amount,reference,paid_at,created_at,observacion',
            'payments.creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse([
            'payment' => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'observacion' => $payment->observacion,
                'created_by' => (int) ($payment->created_by ?? 0),
            ],
            'layaway' => $this->serializeLayawayDetail($layaway),
            'liquidated' => $liquidated,
            'sale_id' => $saleId,
        ], 'Abono registrado correctamente.', 201);
    }

    private function serializeLayawaySummary(Layaway $layaway): array
    {
        $firstPayment = $layaway->payments->sortBy(fn ($payment) => $payment->paid_at ?? $payment->created_at)->first();

        return [
            'id' => (int) $layaway->id,
            'folio' => 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT),
            'status' => $layaway->status,
            'total' => (float) $layaway->subtotal,
            'anticipo_inicial' => $firstPayment ? (float) $firstPayment->amount : 0.0,
            'paid_total' => (float) $layaway->paid_total,
            'saldo_restante' => (float) $layaway->balance,
            'fecha_creacion' => $layaway->created_at?->toIso8601String(),
            'fecha_vencimiento' => $layaway->fecha_vencimiento?->toDateString(),
            'observaciones' => $layaway->observaciones,
            'customer' => $layaway->customer ? [
                'id' => (int) $layaway->customer->id,
                'name' => $layaway->customer->name,
                'phone' => $layaway->customer->phone,
                'email' => $layaway->customer->email,
            ] : null,
            'created_by' => $layaway->creator ? [
                'id' => (int) $layaway->creator->id,
                'name' => $layaway->creator->name,
            ] : null,
        ];
    }

    private function serializeLayawayDetail(Layaway $layaway): array
    {
        $firstPayment = $layaway->payments->sortBy(fn ($payment) => $payment->paid_at ?? $payment->created_at)->first();

        return [
            'id' => (int) $layaway->id,
            'folio' => 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT),
            'status' => $layaway->status,
            'total' => (float) $layaway->subtotal,
            'anticipo_inicial' => $firstPayment ? (float) $firstPayment->amount : 0.0,
            'paid_total' => (float) $layaway->paid_total,
            'saldo_restante' => (float) $layaway->balance,
            'fecha_creacion' => $layaway->created_at?->toIso8601String(),
            'fecha_vencimiento' => $layaway->fecha_vencimiento?->toDateString(),
            'observaciones' => $layaway->observaciones,
            'customer' => $layaway->customer ? [
                'id' => (int) $layaway->customer->id,
                'name' => $layaway->customer->name,
                'phone' => $layaway->customer->phone,
                'email' => $layaway->customer->email,
            ] : null,
            'created_by' => $layaway->creator ? [
                'id' => (int) $layaway->creator->id,
                'name' => $layaway->creator->name,
            ] : null,
            'items' => $layaway->items->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'variant_id' => (int) ($item->product_variant_id ?? 0),
                    'name' => (string) ($item->name ?: ($item->product?->name ?? 'Producto')),
                    'sku' => $item->sku,
                    'qty' => (int) ($item->qty ?? $item->quantity ?? 1),
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => round((float) $item->unit_price * (int) ($item->qty ?? $item->quantity ?? 1), 2),
                    'size' => $item->variant?->size ? [
                        'id' => (int) $item->variant->size->id,
                        'name' => $item->variant->size->name,
                    ] : null,
                    'color' => $item->variant?->color ? [
                        'id' => (int) $item->variant->color->id,
                        'name' => $item->variant->color->name,
                        'hex' => $item->variant->color->hex,
                    ] : null,
                ];
            })->values()->all(),
            'payments' => $layaway->payments->map(function ($payment) {
                return [
                    'id' => (int) $payment->id,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                    'observacion' => $payment->observacion,
                    'created_by' => $payment->creator ? [
                        'id' => (int) $payment->creator->id,
                        'name' => $payment->creator->name,
                    ] : null,
                ];
            })->values()->all(),
            'sale' => $layaway->sale ? [
                'id' => (int) $layaway->sale->id,
                'status' => $layaway->sale->status,
                'total' => (float) $layaway->sale->total,
                'created_at' => $layaway->sale->created_at?->toIso8601String(),
            ] : null,
        ];
    }
}
