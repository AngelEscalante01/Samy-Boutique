<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Services\SalesAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashCutsController extends Controller
{
    public function __construct(private readonly SalesAnalyticsService $salesAnalytics)
    {
    }

    public function index(Request $request)
    {
        $date = (string) $request->query('date', now()->toDateString());

        $summary = null;
        $sales = [];

        if ($request->has('date') || $request->query('preview')) {
            $dailyMetrics = $this->salesAnalytics->buildDailyCutMetrics($date);
            $summary = $dailyMetrics['summary'];
            $sales = $dailyMetrics['sales'];
        }

        $savedCuts = CashCut::query()
            ->with('creator:id,name')
            ->orderByDesc('cut_date')
            ->limit(50)
            ->get()
            ->map(fn (CashCut $cut) => [
                'id' => (int) $cut->id,
                'cut_date' => $cut->cut_date->toDateString(),
                'created_by' => $cut->creator?->name ?? '—',
                'created_at' => $cut->created_at?->toDateTimeString(),
                'totals_json' => $cut->totals_json,
            ]);

        return Inertia::render('CashCuts/Index', [
            'selectedDate' => $date,
            'summary' => $summary,
            'sales' => $sales,
            'savedCuts' => $savedCuts,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $dailyMetrics = $this->salesAnalytics->buildDailyCutMetrics($data['date']);

        return response()->json([
            'summary' => $dailyMetrics['summary'],
            'sales' => $dailyMetrics['sales'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $dailyMetrics = $this->salesAnalytics->buildDailyCutMetrics($data['date']);

        CashCut::query()->updateOrCreate(
            ['cut_date' => $data['date']],
            [
                'created_by' => (int) $request->user()->id,
                'totals_json' => $dailyMetrics['summary'],
            ],
        );

        return back()->with('success', 'Corte guardado correctamente.');
    }

    public function show(CashCut $cashCut)
    {
        $cashCut->load('creator:id,name');

        return Inertia::render('CashCuts/Show', [
            'cashCut' => [
                'id' => (int) $cashCut->id,
                'cut_date' => $cashCut->cut_date->toDateString(),
                'created_by' => $cashCut->creator?->name ?? '—',
                'created_at' => $cashCut->created_at?->toDateTimeString(),
                'totals_json' => $cashCut->totals_json,
            ],
            'parsedTotals' => $cashCut->totals_json ?? [],
        ]);
    }
}
