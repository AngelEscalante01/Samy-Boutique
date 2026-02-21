<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreColorRequest;
use App\Http\Requests\UpdateColorRequest;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ColorController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $colors = Color::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Catalogs/Colors/Index', [
            'filters' => ['q' => $q],
            'colors' => $colors,
        ]);
    }

    public function store(StoreColorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Color::create([
            'name'   => $validated['name'],
            'hex'    => $validated['hex'] ? strtoupper($validated['hex']) : null,
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Color creado.');
    }

    public function update(UpdateColorRequest $request, Color $color): RedirectResponse
    {
        $validated = $request->validated();

        $color->update([
            'name' => $validated['name'],
            'hex' => $validated['hex'] ? strtoupper($validated['hex']) : null,
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Color actualizado.');
    }

    public function toggle(Color $color): RedirectResponse
    {
        $color->update(['active' => ! $color->active]);

        $label = $color->active ? 'activado' : 'desactivado';

        return back()->with('success', "Color {$label}.");
    }

    public function destroy(Color $color): RedirectResponse
    {
        if ($color->products()->exists()) {
            return back()->with('error', 'No puedes eliminar este color porque tiene productos asignados.');
        }

        $color->delete();

        return back()->with('success', 'Color eliminado.');
    }
}
