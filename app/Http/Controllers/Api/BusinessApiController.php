<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sales\CreateSaleAction;
use App\Actions\Sales\PreviewSaleTotalsAction;
use App\Http\Requests\AddLayawayPaymentRequest;
use App\Http\Requests\PreviewSaleRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\StoreLayawayRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CouponResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ProductResource;
use App\Models\CashCut;
use App\Models\Category;
use App\Models\Color;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Layaway;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Size;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\LayawayService;
use App\Services\DailyCutService;
use App\Services\ReportAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessApiController extends ApiController
{
    public function products(Request $request, InventoryService $inventory)
    {
        $includeInactive = $request->boolean('include_inactive') || $request->boolean('include_deleted');
        $query = Product::query()->with(['category', 'size', 'color', 'images', 'variants.size', 'variants.color'])
            ->when(! $includeInactive, fn ($q) => $q->where('status', 'disponible'))
            ->when($request->filled('gender'), fn ($q) => $q->byGender($request->string('gender')->toString()))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->search($request->string('q')->toString())
            ->when($request->filled('updated_since'), fn ($q) => $q->where('updated_at', '>=', Carbon::parse($request->input('updated_since'))->utc()))
            ->when(! $includeInactive, fn ($q) => $inventory->scopeSellableProducts($q))
            ->latest('id');
        $products = $query->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return $this->paginated($products, fn (Product $product) => $this->productData($product, $request), 'Productos obtenidos.');
    }

    private function productData(Product $product, Request $request): array
    {
        $data = (new ProductResource($product))->resolve($request);
        $activeVariants = $product->variants->where('active', true);
        $stock = (int) $activeVariants->sum('stock');
        $data['stock'] = $stock;
        $data['stock_available'] = $stock;
        $data['is_sellable'] = $product->status === 'disponible' && $stock > 0;
        $data['sync_action'] = $data['is_sellable'] ? 'upsert' : 'remove';
        $data['updated_at'] = $product->updated_at?->toISOString();
        $data['sync_updated_at'] = $data['updated_at'];
        $data['sale_price'] = (float) ($product->sale_price_base ?? $product->sale_price ?? 0);
        return $data;
    }

    public function catalog(string $kind)
    {
        $models = ['categories' => Category::class, 'sizes' => Size::class, 'colors' => Color::class];
        abort_unless(isset($models[$kind]), 404);
        $model = $models[$kind];
        $query = $model::query()->orderBy('name');
        if ($kind === 'categories') $query->where('active', true);
        $fields = $kind === 'colors' ? ['id', 'name', 'hex'] : ['id', 'name'];
        return $this->success($query->get($fields), 'Catálogo obtenido.');
    }

    public function customers(Request $request)
    {
        $rows = Customer::query()->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('name', 'like', '%'.$request->input('q').'%')->orWhere('email', 'like', '%'.$request->input('q').'%')->orWhere('phone', 'like', '%'.$request->input('q').'%')))
            ->orderBy('name')->paginate(min(max($request->integer('per_page', 30), 1), 100));
        return $this->paginated($rows, fn (Customer $c) => (new CustomerResource($c))->resolve($request), 'Clientes obtenidos.');
    }

    public function customer(Customer $customer)
    {
        $recentSales = $customer->sales()->latest('id')->limit(10)->get(['id','total','status','created_at'])->map(fn ($sale) => [...$sale->toArray(), 'created_at' => $sale->created_at?->toISOString()]);
        $recentLayaways = $customer->layaways()->latest('id')->limit(10)->get(['id','status','subtotal','paid_total','created_at'])->map(fn ($layaway) => [...$layaway->toArray(), 'created_at' => $layaway->created_at?->toISOString()]);
        return $this->success([
            'customer' => (new CustomerResource($customer))->resolve(request()),
            'recent_sales' => $recentSales,
            'recent_layaways' => $recentLayaways,
            'stats' => ['total_spent' => number_format((float) $customer->sales()->where('status', 'completed')->sum('total'), 2, '.', ''), 'last_sale' => $customer->sales()->latest()->value('created_at')?->toISOString()],
        ], 'Cliente obtenido.');
    }

    public function createCustomer(StoreCustomerRequest $request)
    {
        $v = $request->validated();
        $customer = Customer::create(['name' => $v['name'], 'email' => $v['email'] ?? null, 'phone' => $v['phone'] ?? null, 'active' => (bool) ($v['active'] ?? true)]);
        return $this->success(['customer' => (new CustomerResource($customer))->resolve($request)], 'Cliente creado.', 201);
    }

    public function updateCustomer(UpdateCustomerRequest $request, Customer $customer)
    {
        $v = $request->validated();
        $customer->update(['name' => $v['name'], 'email' => $v['email'] ?? null, 'phone' => $v['phone'] ?? null, 'active' => (bool) ($v['active'] ?? true)]);
        return $this->success(['customer' => (new CustomerResource($customer->fresh()))->resolve($request)], 'Cliente actualizado.');
    }

    public function storeSale(StoreSaleRequest $request, CreateSaleAction $action)
    {
        $sale = $action->execute($request->validated(), $request->user());
        $sale->load(['items.variant.size', 'items.variant.color', 'payments', 'coupon', 'customer', 'creator']);
        return $this->success(['sale' => $this->saleData($sale)], 'Venta registrada.', 201);
    }

    public function previewSale(PreviewSaleRequest $request, PreviewSaleTotalsAction $action)
    {
        return $this->success(['totals' => $action->execute($request->validated(), $request->user())], 'Totales calculados.');
    }

    public function sales(Request $request)
    {
        $rows = Sale::with(['customer:id,name,phone', 'creator:id,name', 'payments'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('id', 'like', $request->input('q').'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$request->input('q').'%')->orWhere('phone', 'like', '%'.$request->input('q').'%'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment_method'), function ($q) use ($request) {
                $method = $request->input('payment_method');
                if ($method === 'mixed') {
                    $q->whereHas('payments', function ($p) {
                        $p->select('sale_id')->groupBy('sale_id')->havingRaw('COUNT(DISTINCT method) > 1');
                        });
                    return;
                }
                $q->whereHas('payments', fn ($p) => $p->where('method', $method));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest()->paginate(min(max($request->integer('per_page', 20), 1), 100));
        return $this->paginated($rows, fn (Sale $sale) => $this->saleSummary($sale), 'Ventas obtenidas.');
    }

    private function saleSummary(Sale $sale): array
    {
        $payments = $sale->payments;
        $methods = $payments->pluck('method')->unique()->values();
        $cash = (float) ($sale->cash_received ?? $payments->where('method', 'cash')->sum('amount'));
        return ['id' => $sale->id, 'folio' => (string) $sale->id, 'status' => $sale->status, 'created_at' => $sale->created_at?->toISOString(), 'total' => (float) $sale->total, 'cash_received' => $cash, 'change' => max(0, $cash - (float) $sale->total), 'items_count' => (int) $sale->items()->sum('qty'), 'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name, 'phone' => $sale->customer->phone] : null, 'cashier' => ['name' => $sale->creator?->name], 'payment' => ['primary_method' => $methods->first() ?? 'other', 'is_mixed' => $methods->count() > 1]];
    }

    private function saleData(Sale $sale): array
    {
        $totalCash = (float) ($sale->cash_received ?? $sale->payments->where('method', 'cash')->sum('amount'));
        return ['id' => $sale->id, 'folio' => (string) $sale->id, 'status' => $sale->status, 'subtotal' => (float) $sale->subtotal, 'discount_total' => (float) $sale->discount_total, 'coupon_discount_total' => (float) $sale->coupon_discount_total, 'loyalty_discount_total' => (float) $sale->loyalty_discount_total, 'total' => (float) $sale->total, 'cash_received' => $totalCash, 'change' => max(0, $totalCash - (float) $sale->total), 'created_at' => $sale->created_at?->toISOString(), 'customer' => $sale->customer ? ['id' => $sale->customer->id, 'name' => $sale->customer->name, 'phone' => $sale->customer->phone, 'email' => $sale->customer->email] : null, 'cashier' => ['name' => $sale->creator?->name], 'items' => $sale->items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'sku' => $i->sku, 'qty' => (int) ($i->qty ?? $i->quantity), 'unit_price' => (float) $i->unit_price, 'discount_amount' => (float) $i->discount_amount, 'line_total' => (float) $i->line_total, 'variant' => $i->variant ? ['size' => ['name' => $i->variant->size?->name], 'color' => ['name' => $i->variant->color?->name]] : null])->values(), 'payments' => $sale->payments->map(fn ($p) => ['id' => $p->id, 'method' => $p->method, 'amount' => (float) $p->amount, 'reference' => $p->reference])->values()];
    }

    public function sale(Sale $sale)
    {
        $sale->load(['customer', 'creator', 'items.variant.size', 'items.variant.color', 'payments']);
        return $this->success($this->saleData($sale), 'Venta obtenida.');
    }

    public function ticket(Sale $sale)
    {
        $sale->load(['customer', 'creator', 'items.variant.size', 'items.variant.color', 'payments']);
        return $this->success(['sale' => $this->saleData($sale), 'business' => \App\Models\Setting::query()->pluck('value', 'key')], 'Ticket obtenido.');
    }

    public function layaways(Request $request)
    {
        $rows = Layaway::with(['customer', 'creator'])->when(in_array($request->input('status'), ['open','liquidated','cancelled'], true), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('id','like','%'.$request->input('q').'%')->orWhereHas('customer', fn ($c) => $c->where('name','like','%'.$request->input('q').'%'))))
            ->latest('id')->paginate(min(max($request->integer('per_page',20),1),100));
        return $this->paginated($rows, fn (Layaway $l) => $this->layawayData($l), 'Apartados obtenidos.');
    }

    private function layawayData(Layaway $l): array
    {
        return ['id' => $l->id, 'folio' => 'AP-'.$l->id, 'status' => $l->status, 'total' => (float) $l->subtotal, 'subtotal' => (float) $l->subtotal, 'anticipo_inicial' => (float) $l->payments->first()?->amount, 'paid_total' => (float) $l->paid_total, 'saldo_restante' => (float) $l->balance, 'fecha_creacion' => $l->created_at?->toISOString(), 'fecha_vencimiento' => null, 'observaciones' => null, 'customer' => $l->customer ? ['id' => $l->customer->id, 'name' => $l->customer->name, 'phone' => $l->customer->phone, 'email' => $l->customer->email] : null, 'created_by' => ['name' => $l->creator?->name], 'items' => $l->items->map(fn ($i) => ['id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'name' => $i->name, 'sku' => $i->sku, 'qty' => (int) ($i->qty ?? $i->quantity), 'unit_price' => (float) $i->unit_price, 'line_total' => (float) $i->unit_price * (int) ($i->qty ?? $i->quantity), 'size' => ['name' => $i->variant?->size?->name], 'color' => ['name' => $i->variant?->color?->name, 'hex' => $i->variant?->color?->hex]])->values(), 'payments' => $l->payments->map(fn ($p) => ['id' => $p->id, 'amount' => (float) $p->amount, 'method' => $p->method, 'reference' => $p->reference, 'paid_at' => $p->paid_at?->toISOString(), 'created_by' => null])->values()];
    }

    private function loadLayaway(Layaway $layaway): Layaway { return $layaway->load(['items.variant.size','items.variant.color','payments','customer','creator']); }

    public function layaway(Layaway $layaway) { return $this->success($this->layawayData($this->loadLayaway($layaway)), 'Apartado obtenido.'); }

    public function createLayaway(StoreLayawayRequest $request, LayawayService $service)
    {
        $data = $request->validated();
        if ($request->filled('anticipo_inicial')) $data['payments'] = [['method' => $request->input('metodo_anticipo','cash'), 'amount' => $request->input('anticipo_inicial'), 'reference' => $request->input('referencia_anticipo')]];
        $layaway = $service->create($data, $request->user());
        return $this->success($this->layawayData($this->loadLayaway($layaway)), 'Apartado creado.', 201);
    }

    public function addLayawayPayment(AddLayawayPaymentRequest $request, Layaway $layaway, LayawayService $service)
    {
        $payment = $service->addPayment($layaway, $request->validated());
        $fresh = $layaway->fresh();
        $liquidated = false;
        $saleId = null;
        if ($request->boolean('auto_liquidate', true) && (float) $fresh->balance <= 0) {
            $sale = $service->liquidate($fresh, ['payments' => []], $request->user());
            $liquidated = true;
            $saleId = $sale->id;
        }
        return $this->success(['payment' => ['id' => $payment->id, 'amount' => (float) $payment->amount, 'method' => $payment->method, 'reference' => $payment->reference, 'paid_at' => $payment->paid_at?->toISOString()], 'layaway' => $this->layawayData($this->loadLayaway($fresh->fresh())), 'liquidated' => $liquidated, 'sale_id' => $saleId], 'Abono registrado.', 201);
    }

    public function cashCuts(Request $request)
    {
        $rows = CashCut::with('creator:id,name')->latest('cut_date')->paginate(min(max($request->integer('per_page',20),1),100));
        return $this->paginated($rows, fn (CashCut $c) => ['id'=>$c->id,'cut_date'=>$c->cut_date->toDateString(),'created_by'=>$c->creator?->name,'created_at'=>$c->created_at?->toISOString(),'totals'=>$c->totals_json], 'Cortes obtenidos.');
    }

    public function previewCashCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate(['date'=>['required','date_format:Y-m-d']]);
        $totals = $dailyCut->totals($data['date']);
        return $this->success(['totals'=>['date'=>$totals['date'],'sales_count'=>$totals['sales_count'],'total_sold'=>(float)$totals['total_sum'],'profit_total'=>0.0,'manual_discount_total'=>(float)$totals['discount_sum'],'coupon_discount_total'=>(float)$totals['coupon_discount_sum'],'loyalty_discount_total'=>(float)$totals['loyalty_discount_sum'],'cancelled_count'=>$totals['cancelled_count'],'payments_by_method'=>array_map('floatval',$totals['payments_by_method']),'top_products'=>$totals['top_products'],'top_categories'=>$totals['top_categories']]], 'Corte previsualizado.');
    }

    public function createCashCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate(['date'=>['required','date_format:Y-m-d']]);
        $totals = $dailyCut->totals($data['date']);
        $stored = ['date'=>$totals['date'],'sales_count'=>$totals['sales_count'],'total_sold'=>(float)$totals['total_sum'],'profit_total'=>0.0,'manual_discount_total'=>(float)$totals['discount_sum'],'coupon_discount_total'=>(float)$totals['coupon_discount_sum'],'loyalty_discount_total'=>(float)$totals['loyalty_discount_sum'],'cancelled_count'=>$totals['cancelled_count'],'payments_by_method'=>array_map('floatval',$totals['payments_by_method']),'top_products'=>$totals['top_products'],'top_categories'=>$totals['top_categories']];
        $cut = DB::transaction(fn () => CashCut::updateOrCreate(['cut_date'=>$data['date']], ['created_by'=>$request->user()->id,'totals_json'=>$stored]));
        $cut->load('creator:id,name');
        return $this->success(['id'=>$cut->id,'cut_date'=>$cut->cut_date->toDateString(),'created_by'=>$cut->creator?->name,'created_at'=>$cut->created_at?->toISOString(),'totals'=>$cut->totals_json], 'Corte guardado.', 201);
    }

    public function coupons(Request $request)
    {
        $rows = Coupon::withCount('redemptions')->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('code','like','%'.$request->input('q').'%')->orWhere('name','like','%'.$request->input('q').'%')))->when($request->has('active'), fn ($q) => $q->where('active',$request->boolean('active')))->latest('id')->paginate(min(max($request->integer('per_page',20),1),100));
        return $this->paginated($rows, fn (Coupon $c) => (new CouponResource($c))->resolve($request), 'Cupones obtenidos.');
    }

    public function users(Request $request)
    {
        $rows = User::with('roles:name')->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('name','like','%'.$request->input('q').'%')->orWhere('email','like','%'.$request->input('q').'%')))->orderBy('name')->paginate(min(max($request->integer('per_page',30),1),100));
        return $this->paginated($rows, fn (User $u) => ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email,'active'=>(bool)$u->active,'roles'=>$u->getRoleNames()->values(),'created_at'=>$u->created_at?->toDateString()], 'Usuarios obtenidos.');
    }

    public function report(Request $request, ReportAnalyticsService $analytics)
    {
        $filters = $request->validate(['from'=>['required','date_format:Y-m-d'],'to'=>['required','date_format:Y-m-d','after_or_equal:from']]);
        $from = Carbon::parse($filters['from'])->startOfDay(); $to = Carbon::parse($filters['to'])->endOfDay();
        $sales = Sale::where('status','completed')->whereBetween('created_at',[$from,$to]);
        $total = (float)(clone $sales)->sum('total'); $count = (int)(clone $sales)->count();
        $summary = ['total_sold'=>$total,'profit_total'=>0.0,'sales_count'=>$count,'canceled_count'=>Sale::where('status','cancelled')->whereBetween('created_at',[$from,$to])->count(),'discount_total'=>(float)(clone $sales)->sum('discount_total'),'manual_discount_total'=>(float)(clone $sales)->sum('discount_total'),'coupon_discount_total'=>(float)(clone $sales)->sum('coupon_discount_total'),'loyalty_discount_total'=>(float)(clone $sales)->sum('loyalty_discount_total')];
        return $this->success(['filters'=>['from'=>$filters['from'],'to'=>$filters['to']],'summary'=>$summary,...$analytics->forRange($from, $to)], 'Reporte obtenido.');
    }

    public function settings()
    {
        return $this->success(['loyalty'=>['enabled'=>(bool)\App\Models\Setting::get('loyalty.enabled',false),'type'=>(string)\App\Models\Setting::get('loyalty.type','percent'),'value'=>(float)\App\Models\Setting::get('loyalty.value',0)]], 'Configuración obtenida.');
    }
}
