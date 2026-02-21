<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CashCutsController extends Controller
{
    /**
     * Pantalla principal: selector de fecha + resumen + historial.
     */
    public function index(Request $request)
    {
        $date = (string) $request->query('date', now()->toDateString());

        // Si ya hay datos generados, los incluimos; si no, null
        $summary = null;
        $sales    = collect();

        if ($request->has('date') || $request->query('preview')) {
            ['summary' => $summary, 'sales' => $sales] = $this->buildPreview($date);
        }

        $savedCuts = CashCut::query()
            ->with('creator:id,name')
            ->orderByDesc('cut_date')
            ->limit(50)
            ->get()
            ->map(fn (CashCut $c) => [
                'id'         => $c->id,
                'cut_date'   => $c->cut_date->toDateString(),
                'created_by' => $c->creator?->name ?? '—',
                'created_at' => $c->created_at?->toDateTimeString(),
                'totals_json' => $c->totals_json,
            ]);

        return Inertia::render('CashCuts/Index', [
            'selectedDate' => $date,
            'summary'      => $summary,
            'sales'        => $sales,
            'savedCuts'    => $savedCuts,
        ]);
    }

    /**
     * AJAX: previsualizar resumen sin guardar.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        ['summary' => $summary, 'sales' => $sales] = $this->buildPreview($data['date']);

        return response()->json([
            'summary' => $summary,
            'sales'   => $sales,
        ]);
    }

    /**
     * Guardar corte del día.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        ['summary' => $summary] = $this->buildPreview($data['date']);

        CashCut::updateOrCreate(
            ['cut_date' => $data['date']],
            [
                'created_by'  => (int) $request->user()->id,
                'totals_json' => $summary,
            ],
        );

        return back()->with('success', 'Corte guardado correctamente.');
    }

    /**
     * Ver detalle de un corte guardado.
     */
    public function show(CashCut $cashCut)
    {
        $cashCut->load('creator:id,name');

        return Inertia::render('CashCuts/Show', [
            'cashCut'      => [
                'id'          => $cashCut->id,
                'cut_date'    => $cashCut->cut_date->toDateString(),
                'created_by'  => $cashCut->creator?->name ?? '—',
                'created_at'  => $cashCut->created_at?->toDateTimeString(),
                'totals_json' => $cashCut->totals_json,
            ],
            'parsedTotals' => $cashCut->totals_json ?? [],
        ]);
    }

    /* ────────────────────────────────────────────────────────────────────────
       PRIVADO: construye el resumen + lista de ventas del día
       ──────────────────────────────────────────────────────────────────────── */
    private function buildPreview(string $date): array
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date' => ['Fecha inválida.']]);
        }

        $start = $day->copy();
        $end   = $day->copy()->endOfDay();

        // ── Ventas completadas ────────────────────────────────────────────────
        $salesBase = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        $salesCount = (int) (clone $salesBase)->count();

        $totals = (clone $salesBase)
            ->selectRaw('COALESCE(SUM(subtotal),0)               as subtotal_sum')
            ->selectRaw('COALESCE(SUM(discount_total),0)         as discount_sum')
            ->selectRaw('COALESCE(SUM(coupon_discount_total),0)  as coupon_discount_sum')
            ->selectRaw('COALESCE(SUM(loyalty_discount_total),0) as loyalty_discount_sum')
            ->selectRaw('COALESCE(SUM(total),0)                  as total_sum')
            ->first();

        // ── Canceladas ────────────────────────────────────────────────────────
        $canceledCount = (int) Sale::query()
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // ── Pagos por método ──────────────────────────────────────────────────
        $paymentRows = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$start, $end])
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method as method, COALESCE(SUM(sale_payments.amount),0) as amount')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->method => (float) $r->amount])
            ->all();

        foreach (['cash', 'card', 'transfer', 'other'] as $m) {
            $paymentRows[$m] = $paymentRows[$m] ?? 0.0;
        }

        // ── Lista de ventas del día ───────────────────────────────────────────
        $sales = Sale::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['payments', 'customer:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $s) => [
                'id'         => $s->id,
                'status'     => $s->status,
                'total'      => (float) $s->total,
                'created_at' => $s->created_at?->toDateTimeString(),
                'customer'   => $s->customer?->name,
                'methods'    => $s->payments->pluck('method')->unique()->values(),
            ]);

        $summary = [
            'date'                   => $date,
            'sales_count'            => $salesCount,
            'canceled_count'         => $canceledCount,
            'total_sales'            => (float) $totals->total_sum,
            'subtotal_sum'           => (float) $totals->subtotal_sum,
            'discount_manual_total'  => (float) $totals->discount_sum,
            'discount_coupon_total'  => (float) $totals->coupon_discount_sum,
            'discount_loyalty_total' => (float) $totals->loyalty_discount_sum,
            'payments'               => $paymentRows,
        ];

        return compact('summary', 'sales');
    }
}
