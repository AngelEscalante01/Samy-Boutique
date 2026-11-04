<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Models\Occasion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OccasionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Catalogs/Occasions/Index', [
            'occasions' => Occasion::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['required', 'boolean'],
        ]);
        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']));
        $data['normalized_name'] = Occasion::normalizeName($data['name']);
        $this->assertUniqueName($data['normalized_name']);
        Occasion::create($data);

        return back()->with('success', 'Ocasión creada.');
    }

    public function update(Request $request, Occasion $occasion): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['required', 'boolean'],
        ]);
        $data['name'] = trim(preg_replace('/\s+/u', ' ', $data['name']));
        $data['normalized_name'] = Occasion::normalizeName($data['name']);
        $this->assertUniqueName($data['normalized_name'], $occasion->id);
        $occasion->update($data);

        return back()->with('success', 'Ocasión actualizada.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1', 'max:200'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'occasion_ids' => ['present', 'array'],
            'occasion_ids.*' => ['integer', 'distinct', 'exists:occasions,id'],
        ]);

        DB::transaction(function () use ($data) {
            $ids = collect($data['occasion_ids'])->map(fn ($id) => (int) $id)->all();
            foreach (\App\Models\Product::query()->whereKey($data['product_ids'])->lockForUpdate()->get() as $product) {
                $product->occasions()->sync($ids);
            }
        });

        return back()->with('success', 'Ocasiones asignadas a los productos.');
    }

    private function assertUniqueName(string $normalized, ?int $exceptId = null): void
    {
        if ($normalized === '' || Occasion::query()->where('normalized_name', $normalized)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe una ocasión con este nombre.']);
        }
    }
}
