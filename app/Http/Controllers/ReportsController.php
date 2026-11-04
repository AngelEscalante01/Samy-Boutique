<?php

namespace App\Http\Controllers;

use App\Models\CashCut;
use App\Http\Requests\ReportRangeRequest;
use App\Services\DailyCutService;
use App\Services\ReportDataService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    /**
     * Página principal de reportes con filtros de rango de fechas.
     */
    public function index(ReportRangeRequest $request, ReportDataService $reports)
    {
        return Inertia::render('Reports/Index', $reports->build($request->validated('from'), $request->validated('to')));
    }

    public function export(ReportRangeRequest $request, string $format, ReportDataService $reports, ReportExportService $exports)
    {
        $data = $reports->build($request->validated('from'), $request->validated('to'));
        $name = 'Samy-reporte-'.$data['filters']['from'].'-al-'.$data['filters']['to'];
        $headers = ['Cache-Control' => 'private, no-store'];

        if ($format === 'pdf') {
            return response($exports->pdf($data), 200, $headers + [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"',
            ]);
        }

        return response()->download($exports->xlsx($data), $name.'.xlsx', $headers + [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function dailyCut(Request $request)
    {
        $date = (string) $request->query('date', now()->toDateString());

        $cut = CashCut::query()
            ->where('cut_date', $date)
            ->with('creator')
            ->first();

        return Inertia::render('Reports/DailyCut', [
            'filters' => [
                'date' => $date,
            ],
            'savedCut' => $cut,
        ]);
    }

    public function previewDailyCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'totals' => $dailyCut->totals($data['date']),
        ]);
    }

    public function saveDailyCut(Request $request, DailyCutService $dailyCut)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $totals = $dailyCut->totals($data['date']);

        $cut = CashCut::query()->updateOrCreate(
            ['cut_date' => $data['date']],
            [
                'created_by' => (int) $request->user()->id,
                'totals_json' => $totals,
            ],
        );

        if ($request->header('X-Inertia')) {
            return redirect()
                ->route('reports.dailyCut', ['date' => $data['date']])
                ->with('success', 'Corte guardado.');
        }

        return response()->json(['cash_cut' => $cut], 201);
    }

}
