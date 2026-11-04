<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogImportService;
use App\Services\CatalogTemplateWorkbook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogImportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Products/Import', [
            'recent' => \Illuminate\Support\Facades\DB::table('catalog_import_batches')->where('user_id', $request->user()->id)->latest()->limit(20)->get(['id', 'filename', 'created_at']),
            'missingCategoryPrefixes' => Category::query()->where('active', true)->orderBy('name')->get(['name', 'sku_prefix'])
                ->filter(fn (Category $category) => blank($category->sku_prefix))->pluck('name')->values()->all(),
        ]);
    }

    public function preview(Request $request, CatalogImportService $imports)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120'], 'mode' => ['required', 'in:create,update']]);
        $mode = (string) $request->input('mode');
        abort_if($mode === 'update' && ! $request->user()->can('products.update'), 403);
        $id = $imports->preview($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName(), $request->user(), $mode);

        return response()->json(['success' => true, 'data' => $imports->view($id, $request->user())]);
    }

    public function show(Request $request, string $batch, CatalogImportService $imports)
    {
        return response()->json(['success' => true, 'data' => $imports->view($batch, $request->user())]);
    }

    public function confirm(Request $request, string $batch, CatalogImportService $imports)
    {
        $data = $request->validate(['groups' => ['required', 'array', 'min:1', 'max:1000'], 'groups.*' => ['required', 'integer', 'distinct']]);
        $imports->confirm($batch, $data['groups'], $request->user());

        return response()->json(['success' => true, 'data' => $imports->view($batch, $request->user())], 202);
    }

    public function template(Request $request, CatalogTemplateWorkbook $workbook)
    {
        $costs = $request->user()->can('products.view_purchase_price');
        $name = $costs ? 'catalog-create-costs.xlsx' : 'catalog-create.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'samy-template-');
        if ($temp === false || ! copy(resource_path('import-templates/'.$name), $temp)) {
            abort(500, 'No se pudo preparar la plantilla.');
        }
        try {
            $workbook->customize($temp, costs: $costs);

            return response()->download($temp, 'Samy-productos-nuevos.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        } catch (\Throwable $error) {
            @unlink($temp);
            throw $error;
        }
    }

    public function exportUpdate(Request $request, CatalogTemplateWorkbook $workbook)
    {
        abort_unless($request->user()->can('products.update'), 403);
        $ids = collect((array) $request->input('ids', []))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->count() > 500) abort(422, 'Máximo 500 productos por exportación.');
        $query = Product::query()->with(['category', 'occasions', 'variants.size', 'variants.color'])->orderBy('id');
        if ($ids->isNotEmpty()) $query->whereIn('id', $ids);
        $products = $query->limit(501)->get();
        if ($products->count() > 500 || $products->sum(fn ($product) => max(1, $product->variants->count())) > 1000) {
            abort(422, 'Selecciona menos productos para no exceder 1000 filas.');
        }
        $temp = tempnam(sys_get_temp_dir(), 'samy-update-');
        if ($temp === false || ! copy(resource_path('import-templates/catalog-update.xlsx'), $temp)) abort(500);
        try {
            $rows = [\App\Services\CatalogXlsxReader::UPDATE_HEADERS];
            foreach ($products as $product) {
                $occasionSlots = array_pad($product->occasions->sortBy('id')->take(3)->pluck('name')->values()->all(), 3, '');
                foreach ($product->variants as $variant) {
                    $rows[] = [
                        $product->id, $variant->id, $product->updated_at?->format('Y-m-d H:i:s'),
                        $product->category?->name, $product->name, $product->description, $product->gender,
                        $variant->size?->name, $variant->color?->name, $variant->sale_price,
                        $request->user()->can('products.view_purchase_price') ? $variant->purchase_price : '',
                        ...$occasionSlots,
                    ];
                }
            }
            $workbook->customize($temp, update: true, productRows: $rows);

            return response()->download($temp, 'Samy-productos-actualizar.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        } catch (\Throwable $error) {
            @unlink($temp);
            throw $error;
        }
    }

}
