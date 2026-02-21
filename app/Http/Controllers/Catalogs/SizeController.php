<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSizeRequest;
use App\Http\Requests\UpdateSizeRequest;
use App\Models\Size;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SizeController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $sizes = Size::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Catalogs/Sizes/Index', [
            'filters' => ['q' => $q],
            'sizes' => $sizes,
        ]);
    }

    public function store(StoreSizeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Size::create([
            'name' => $validated['name'],
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Talla creada.');
    }

    public function update(UpdateSizeRequest $request, Size $size): RedirectResponse
    {
        $validated = $request->validated();

        $size->update([
            'name' => $validated['name'],
            'active' => (bool) $validated['active'],
        ]);

        return back()->with('success', 'Talla actualizada.');
    }

    public function toggle(Size $size): RedirectResponse
    {
        $size->update(['active' => ! $size->active]);

        $label = $size->active ? 'activada' : 'desactivada';

        return back()->with('success', "Talla {$label}.");
    }

    public function destroy(Size $size): RedirectResponse
    {
        if ($size->products()->exists()) {
            return back()->with('error', 'No puedes eliminar esta talla porque tiene productos asignados.');
        }

        $size->delete();

        return back()->with('success', 'Talla eliminada.');
    }
}
