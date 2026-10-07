<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyCutService
{
    public function totals(string $date): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => ['Fecha inválida.']]);
        }
        $end = $start->copy()->endOfDay();
        $sales = Sale::query()->where('status', 'completed')->whereBetween('created_at', [$start, $end]);
        $amounts = (clone $sales)->selectRaw('COALESCE(SUM(subtotal),0) as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_total),0) as discount_sum')
            ->selectRaw('COALESCE(SUM(coupon_discount_total),0) as coupon_discount_sum')
            ->selectRaw('COALESCE(SUM(loyalty_discount_total),0) as loyalty_discount_sum')
            ->selectRaw('COALESCE(SUM(total),0) as total_sum')->first();
        $payments = DB::table('sale_payments')->join('sales','sale_payments.sale_id','=','sales.id')
            ->where('sales.status','completed')->whereBetween('sales.created_at',[$start,$end])->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, COALESCE(SUM(sale_payments.amount),0) as amount')->get()
            ->mapWithKeys(fn ($r) => [$r->method => number_format((float)$r->amount,2,'.','')])->all();
        foreach (['cash','card','transfer','other'] as $method) $payments[$method] ??= '0.00';
        $topProducts = DB::table('sale_items')->join('sales','sale_items.sale_id','=','sales.id')->where('sales.status','completed')
            ->whereBetween('sales.created_at',[$start,$end])->groupBy('sale_items.product_id','sale_items.sku','sale_items.name')
            ->selectRaw('sale_items.sku as sku, sale_items.name as name, SUM(sale_items.quantity) as qty, COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')->limit(10)->get();
        $topCategories = DB::table('sale_items')->join('sales','sale_items.sale_id','=','sales.id')->join('products','sale_items.product_id','=','products.id')
            ->leftJoin('categories','products.category_id','=','categories.id')->where('sales.status','completed')->whereBetween('sales.created_at',[$start,$end])
            ->groupBy('categories.id','categories.name')->selectRaw("COALESCE(categories.name, 'Sin categoría') as name, SUM(sale_items.quantity) as qty, COALESCE(SUM(sale_items.line_total),0) as total")
            ->orderByDesc('total')->limit(10)->get();
        $cancelledSales = Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->select(['id', 'total', 'created_at', 'cancel_reason'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'total' => (float) $sale->total,
                'created_at' => $sale->created_at?->toDateTimeString(),
                'cancel_reason' => $sale->cancel_reason,
            ])->values();
        return ['date'=>$date,'sales_count'=>(int)(clone $sales)->count(),'subtotal_sum'=>number_format((float)$amounts->subtotal_sum,2,'.',''),
            'discount_sum'=>number_format((float)$amounts->discount_sum,2,'.',''),'coupon_discount_sum'=>number_format((float)$amounts->coupon_discount_sum,2,'.',''),
            'loyalty_discount_sum'=>number_format((float)$amounts->loyalty_discount_sum,2,'.',''),'total_sum'=>number_format((float)$amounts->total_sum,2,'.',''),
            'payments_by_method'=>$payments,'top_products'=>$topProducts,'top_categories'=>$topCategories,
            'cancelled_count'=>$cancelledSales->count(),'cancelled_sales'=>$cancelledSales];
    }
}
