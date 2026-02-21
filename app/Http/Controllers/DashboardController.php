<?php

namespace App\Http\Controllers;

use App\Models\Layaway;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $isManager = Auth::user()?->hasRole('gerente') ?? false;
        $today = now()->toDateString();

        // ── Stats ─────────────────────────────────────────────────────────────
        $salesToday = Sale::whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled');

        $stats = [
            'sales_total_today'  => (float) (clone $salesToday)->sum('total'),
            'sales_count_today'  => (clone $salesToday)->count(),
            'layaways_active'    => Layaway::where('status', 'active')->count(),
            'products_available' => Product::where('status', 'available')->count(),
        ];

        // Ganancia estimada (solo gerente)
        if ($isManager) {
            $saleIds = (clone $salesToday)->pluck('id');
            $profit = \DB::table('sale_items')
                ->join('products', 'sale_items.product_id', '=', 'products.id')
                ->whereIn('sale_items.sale_id', $saleIds)
                ->selectRaw('SUM(sale_items.unit_price - products.purchase_price) as profit')
                ->value('profit');

            $stats['profit_today'] = (float) ($profit ?? 0);
        }

        // ── Actividad reciente ────────────────────────────────────────────────
        $recentSales = Sale::with('customer')
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id'           => $s->id,
                'folio'        => str_pad($s->id, 6, '0', STR_PAD_LEFT),
                'customer'     => $s->customer?->name ?? 'Público general',
                'total'        => (float) $s->total,
                'time'         => $s->created_at->format('H:i'),
                'date'         => $s->created_at->toDateString(),
            ]);

        $recentLayaways = Layaway::with('customer')
            ->where('status', 'active')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($l) => [
                'id'        => $l->id,
                'folio'     => str_pad($l->id, 6, '0', STR_PAD_LEFT),
                'customer'  => $l->customer?->name ?? '—',
                'subtotal'  => (float) $l->subtotal,
                'paid'      => (float) $l->paid_total,
                'balance'   => (float) ($l->subtotal - $l->paid_total),
            ]);

        return Inertia::render('Dashboard', [
            'stats'           => $stats,
            'recentSales'     => $recentSales,
            'recentLayaways'  => $recentLayaways,
            'isManager'       => $isManager,
        ]);
    }
}
