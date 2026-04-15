<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\AddLayawayPaymentRequest;
use App\Http\Requests\LiquidateLayawayRequest;
use App\Http\Requests\StoreLayawayRequest;
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
            'vigencia' => ['nullable', 'in:all,expired,upcoming'],
            'upcoming_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = (string) ($validated['status'] ?? 'open');
        $q = trim((string) ($validated['q'] ?? ''));
        $vigencia = (string) ($validated['vigencia'] ?? 'all');
        $upcomingDays = (int) ($validated['upcoming_days'] ?? 7);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = Layaway::query()->with(['customer:id,name,phone,email', 'creator:id,name']);

        if (in_array($status, ['open', 'liquidated', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($like) {
                $sub->where('id', 'like', $like)
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', $like));
            });
        }

        $today = Carbon::today();
        if ($vigencia === 'expired') {
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', $today);
        } elseif ($vigencia === 'upcoming') {
            $query->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '>=', $today)
                ->whereDate('fecha_vencimiento', '<=', $today->copy()->addDays($upcomingDays));
        }

        $layaways = $query
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $items = $layaways->getCollection()
            ->map(fn (Layaway $layaway) => $this->serializeLayawayListItem($layaway))
            ->values()
            ->all();

        return $this->paginatedResponse($layaways, $items, 'Apartados obtenidos correctamente.');
    }

    public function show(Layaway $layaway, Request $request): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $layaway->load([
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'payments.creator:id,name',
            'customer:id,name,phone,email',
            'creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse($this->serializeLayawayDetail($layaway), 'Apartado obtenido correctamente.');
    }

    public function store(StoreLayawayRequest $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $layaway = $service->create($request->validated(), $request->user());

        return $this->successResponse(
            $this->serializeLayawayDetail($layaway),
            'Apartado creado correctamente.',
            201
        );
    }

    public function addPayment(Layaway $layaway, AddLayawayPaymentRequest $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $payment = $service->addPayment($layaway, $request->validated(), $request->user());

        $layaway->refresh()->load([
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'payments.creator:id,name',
            'customer:id,name,phone,email',
            'creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse([
            'payment' => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toISOString(),
            ],
            'layaway' => $this->serializeLayawayDetail($layaway),
        ], 'Abono registrado correctamente.', 201);
    }

    public function liquidate(Layaway $layaway, LiquidateLayawayRequest $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $sale = $service->liquidate($layaway, $request->validated(), $request->user());

        return $this->successResponse([
            'sale_id' => (int) $sale->id,
            'sale_total' => (float) $sale->total,
            'sale_status' => $sale->status,
            'layaway_id' => (int) $layaway->id,
        ], 'Apartado liquidado correctamente.');
    }

    public function cancel(Layaway $layaway, Request $request, LayawayService $service): JsonResponse
    {
        $this->ensurePermission($request, 'pos.view');

        $cancelled = $service->cancel($layaway);

        $cancelled->load([
            'items.product.images',
            'items.variant.size',
            'items.variant.color',
            'payments.creator:id,name',
            'customer:id,name,phone,email',
            'creator:id,name',
            'sale:id,total,status,created_at',
        ]);

        return $this->successResponse(
            $this->serializeLayawayDetail($cancelled),
            'Apartado cancelado correctamente.'
        );
    }

    private function serializeLayawayListItem(Layaway $layaway): array
    {
        return [
            'id' => (int) $layaway->id,
            'folio' => 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT),
            'status' => $layaway->status,
            'subtotal' => (float) $layaway->subtotal,
            'paid_total' => (float) $layaway->paid_total,
            'balance' => (float) $layaway->balance,
            'vigencia_dias' => (int) ($layaway->vigencia_dias ?? 0),
            'fecha_vencimiento' => $layaway->fecha_vencimiento?->toDateString(),
            'estado_vigencia' => $layaway->estado_vigencia,
            'created_at' => $layaway->created_at?->toISOString(),
            'customer' => $layaway->customer ? [
                'id' => (int) $layaway->customer->id,
                'name' => $layaway->customer->name,
                'phone' => $layaway->customer->phone,
                'email' => $layaway->customer->email,
            ] : null,
            'creator' => $layaway->creator ? [
                'id' => (int) $layaway->creator->id,
                'name' => $layaway->creator->name,
            ] : null,
        ];
    }

    private function serializeLayawayDetail(Layaway $layaway): array
    {
        return [
            'id' => (int) $layaway->id,
            'folio' => 'AP-'.str_pad((string) $layaway->id, 5, '0', STR_PAD_LEFT),
            'status' => $layaway->status,
            'subtotal' => (float) $layaway->subtotal,
            'paid_total' => (float) $layaway->paid_total,
            'balance' => (float) $layaway->balance,
            'vigencia_dias' => (int) ($layaway->vigencia_dias ?? 0),
            'fecha_vencimiento' => $layaway->fecha_vencimiento?->toDateString(),
            'estado_vigencia' => $layaway->estado_vigencia,
            'created_at' => $layaway->created_at?->toISOString(),
            'updated_at' => $layaway->updated_at?->toISOString(),
            'liquidated_at' => $layaway->liquidated_at?->toISOString(),
            'cancelled_at' => $layaway->cancelled_at?->toISOString(),
            'customer' => $layaway->customer ? [
                'id' => (int) $layaway->customer->id,
                'name' => $layaway->customer->name,
                'phone' => $layaway->customer->phone,
                'email' => $layaway->customer->email,
            ] : null,
            'creator' => $layaway->creator ? [
                'id' => (int) $layaway->creator->id,
                'name' => $layaway->creator->name,
            ] : null,
            'sale' => $layaway->sale ? [
                'id' => (int) $layaway->sale->id,
                'total' => (float) $layaway->sale->total,
                'status' => $layaway->sale->status,
                'created_at' => $layaway->sale->created_at?->toISOString(),
            ] : null,
            'items' => $layaway->items->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'product_variant_id' => (int) ($item->product_variant_id ?? 0),
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'qty' => (int) ($item->qty ?? $item->quantity ?? 1),
                    'unit_price' => (float) $item->unit_price,
                    'product' => $item->product ? [
                        'id' => (int) $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'status' => $item->product->status,
                        'images' => $item->product->images->map(fn ($image) => [
                            'id' => (int) $image->id,
                            'path' => $image->path,
                            'url' => asset('storage/'.$image->path),
                        ])->values()->all(),
                    ] : null,
                    'variant' => $item->variant ? [
                        'id' => (int) $item->variant->id,
                        'sku' => $item->variant->sku,
                        'size' => $item->variant->size ? [
                            'id' => (int) $item->variant->size->id,
                            'name' => $item->variant->size->name,
                        ] : null,
                        'color' => $item->variant->color ? [
                            'id' => (int) $item->variant->color->id,
                            'name' => $item->variant->color->name,
                            'hex' => $item->variant->color->hex,
                        ] : null,
                    ] : null,
                ];
            })->values()->all(),
            'payments' => $layaway->payments->map(fn ($payment) => [
                'id' => (int) $payment->id,
                'method' => $payment->method,
                'amount' => (float) $payment->amount,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
                'created_by' => $payment->creator ? [
                    'id' => (int) $payment->creator->id,
                    'name' => $payment->creator->name,
                ] : null,
            ])->values()->all(),
        ];
    }
}
