<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $categories = Category::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Catalogs/Categories/Index', [
            'filters' => ['q' => $q],
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Category::create([
            'name' => $validated['name'],
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        $category->update([
            'name' => $validated['name'],
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Categoría actualizada.');
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['active' => ! $category->active]);

        $label = $category->active ? 'activada' : 'desactivada';

        return back()->with('success', "Categoría {$label}.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'No puedes eliminar esta categoría porque tiene productos asignados.');
        }

        $category->delete();

        return back()->with('success', 'Categoría eliminada.');
    }
}
