<?php

namespace App\Http\Controllers;

use App\Actions\Sales\CreateSaleAction;
use App\Http\Requests\StoreSaleRequest;
use App\Models\ProductVariant;
use App\Models\SyncReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncSalesController extends Controller
{
    public function store(Request $request, CreateSaleAction $action): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'uuid' => ['required', 'uuid'],
            'payload' => ['required', 'array'],
        ])->validate();

        $uuid = (string) $validated['uuid'];

        $existing = SyncReceipt::query()->where('uuid', $uuid)->first();
        if ($existing && $existing->status === 'synced') {
            return response()->json([
                'status' => 'already_synced',
                'message' => 'Esta transacción ya fue sincronizada.',
                'receipt' => $existing,
            ]);
        }

        $payload = $validated['payload'];

        $salePayloadValidator = Validator::make($payload, (new StoreSaleRequest())->rules());
        if ($salePayloadValidator->fails()) {
            $errors = $salePayloadValidator->errors()->toArray();

            $receipt = SyncReceipt::query()->updateOrCreate(['uuid' => $uuid], [
                'uuid' => $uuid,
                'type' => 'sale',
                'status' => 'error',
                'response_json' => [
                    'message' => 'Payload inválido para sincronización.',
                    'errors' => $errors,
                ],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Payload inválido para sincronización.',
                'errors' => $errors,
                'receipt_id' => $receipt->id,
            ], 422);
        }

        try {
            $sale = $action->execute($salePayloadValidator->validated(), $request->user());

            $receipt = SyncReceipt::query()->updateOrCreate(['uuid' => $uuid], [
                'uuid' => $uuid,
                'type' => 'sale',
                'status' => 'synced',
                'response_json' => [
                    'sale_id' => $sale->id,
                    'message' => 'Venta sincronizada correctamente.',
                ],
            ]);

            return response()->json([
                'status' => 'synced',
                'message' => 'Venta sincronizada correctamente.',
                'sale_id' => $sale->id,
                'folio' => (string) $sale->id,
                'receipt_id' => $receipt->id,
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $conflicts = $this->detectConflictsFromPayload($payload);
            $isConflict = count($conflicts) > 0;

            $receipt = SyncReceipt::query()->updateOrCreate(['uuid' => $uuid], [
                'uuid' => $uuid,
                'type' => 'sale',
                'status' => $isConflict ? 'conflict' : 'error',
                'response_json' => [
                    'message' => $isConflict
                        ? 'No se pudo sincronizar: uno o más productos ya no están disponibles.'
                        : 'No se pudo sincronizar la venta.',
                    'errors' => $errors,
                    'conflicts' => $conflicts,
                ],
            ]);

            return response()->json([
                'status' => $isConflict ? 'conflict' : 'error',
                'message' => $isConflict
                    ? 'No se pudo sincronizar: uno o más productos ya no están disponibles.'
                    : 'No se pudo sincronizar la venta.',
                'errors' => $errors,
                'conflicts' => $conflicts,
                'receipt_id' => $receipt->id,
            ], $isConflict ? 409 : 422);
        }
    }

    private function detectConflictsFromPayload(array $payload): array
    {
        $variantIds = collect(Arr::get($payload, 'items', []))
            ->pluck('variant_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            return [];
        }

        return ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->where(function ($query) {
                $query->where('active', false)
                    ->orWhere('stock', '<=', 0);
            })
            ->with('product:id,name')
            ->get(['id', 'sku', 'product_id', 'stock', 'active'])
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product?->name,
                'active' => (bool) $variant->active,
                'stock' => (int) $variant->stock,
            ])
            ->values()
            ->all();
    }
}
