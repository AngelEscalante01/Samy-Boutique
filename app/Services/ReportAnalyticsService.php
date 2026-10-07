<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportAnalyticsService
{
    public function forRange(Carbon $start, Carbon $end): array
    {
        $topCategories = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("COALESCE(categories.name, 'Sin categoría') as name")
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'qty' => (int) $row->qty,
                'total' => (float) $row->total,
            ])->values();

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_items.product_id', 'sale_items.sku', 'sale_items.name')
            ->selectRaw('sale_items.sku as sku')
            ->selectRaw('sale_items.name as name')
            ->selectRaw('SUM(sale_items.quantity) as qty')
            ->selectRaw('COALESCE(SUM(sale_items.line_total),0) as total')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'sku' => $row->sku,
                'name' => $row->name,
                'qty' => (int) $row->qty,
                'total' => (float) $row->total,
            ])->values();

        $topCustomers = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->selectRaw('customers.name as name')
            ->selectRaw('customers.phone as phone')
            ->selectRaw('COUNT(*) as purchases_in_range')
            ->selectRaw('COALESCE(SUM(sales.total),0) as total_spent')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'phone' => $row->phone,
                'purchases_in_range' => (int) $row->purchases_in_range,
                'total_spent' => (float) $row->total_spent,
            ])->values();

        $nearLoyaltyCustomers = Customer::query()
            ->where('purchases_count', 4)
            ->select(['id', 'name', 'phone', 'purchases_count'])
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn (Customer $customer) => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'purchases_count' => (int) $customer->purchases_count,
            ])->values();

        return [
            'top_categories' => $topCategories,
            'top_products' => $topProducts,
            'top_customers' => $topCustomers,
            'near_loyalty_customers' => $nearLoyaltyCustomers,
        ];
    }
}
