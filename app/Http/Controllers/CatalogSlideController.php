<?php

namespace App\Http\Controllers;

use App\Models\CatalogSlide;
use App\Services\ProductImageReferenceGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogSlideController extends Controller
{
    public function __construct(private readonly ProductImageReferenceGuard $referenceGuard) {}

    public function index(): Response
    {
        return Inertia::render('CatalogSlides/Index', [
            'slides' => CatalogSlide::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn ($slide) => $this->payload($slide)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $disk = $this->disk();
        $generation = (string) Str::uuid();
        $paths = [];
        try {
            foreach (['desktop', 'mobile'] as $size) {
                if ($request->hasFile($size.'_image')) {
                    $path = $request->file($size.'_image')->storeAs('samy/carousel', $generation.'-'.$size.'.'.$request->file($size.'_image')->extension(), $disk);
                    if (! $path || ! Storage::disk($disk)->exists($path)) {
                        throw new \RuntimeException('No se pudo guardar la imagen del carrusel.');
                    }
                    $paths[$size.'_path'] = $path;
                    $paths[$size.'_disk'] = $disk;
                }
            }
            CatalogSlide::create($data + $paths + ['generation' => $generation]);
        } catch (\Throwable $error) {
            foreach ($paths as $key => $path) {
                if (str_ends_with($key, '_path')) Storage::disk($disk)->delete($path);
            }
            throw $error;
        }

        return back()->with('success', 'Diapositiva creada.');
    }

    public function update(Request $request, CatalogSlide $slide): RedirectResponse
    {
        $data = $this->validated($request, false);
        $disk = $this->disk();
        $generation = (string) Str::uuid();
        $paths = [];
        $old = [];
        try {
            foreach (['desktop', 'mobile'] as $size) {
                if ($request->hasFile($size.'_image')) {
                    $path = $request->file($size.'_image')->storeAs('samy/carousel', $generation.'-'.$size.'.'.$request->file($size.'_image')->extension(), $disk);
                    if (! $path || ! Storage::disk($disk)->exists($path)) {
                        throw new \RuntimeException('No se pudo guardar la imagen del carrusel.');
                    }
                    $paths[$size.'_path'] = $path;
                    $paths[$size.'_disk'] = $disk;
                    $old[] = [$slide->{$size.'_disk'}, $slide->{$size.'_path'}];
                }
            }
            $slide->update($data + $paths + ($paths ? ['generation' => $generation] : []));
        } catch (\Throwable $error) {
            foreach ($paths as $key => $path) {
                if (str_ends_with($key, '_path')) Storage::disk($disk)->delete($path);
            }
            throw $error;
        }
        foreach ($old as [$oldDisk, $oldPath]) {
            if ($oldPath && in_array($oldDisk, ['public', 'r2_public'], true)
                && ! $this->referenceGuard->reasonForObject($oldDisk, $oldPath)) {
                Storage::disk($oldDisk)->delete($oldPath);
            }
        }

        return back()->with('success', 'Diapositiva actualizada.');
    }

    public function destroy(CatalogSlide $slide): RedirectResponse
    {
        $files = [[$slide->desktop_disk, $slide->desktop_path], [$slide->mobile_disk, $slide->mobile_path]];
        $slide->delete();
        foreach ($files as [$disk, $path]) {
            if ($path && in_array($disk, ['public', 'r2_public'], true)
                && ! $this->referenceGuard->reasonForObject($disk, $path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        return back()->with('success', 'Diapositiva eliminada.');
    }

    private function validated(Request $request, bool $create): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'destination' => ['nullable', 'string', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['required', 'boolean'],
            'desktop_image' => [$create ? 'required' : 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:6144'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:6144'],
        ]);
        $destination = trim((string) ($data['destination'] ?? ''));
        if ($destination !== '' && ! (str_starts_with($destination, '/') && ! str_starts_with($destination, '//'))
            && ! (str_starts_with($destination, 'https://') && filter_var($destination, FILTER_VALIDATE_URL))) {
            throw ValidationException::withMessages(['destination' => 'Usa una ruta interna o una URL HTTPS válida.']);
        }
        unset($data['desktop_image'], $data['mobile_image']);
        $data['destination'] = $destination ?: null;

        return $data;
    }

    private function disk(): string
    {
        $disk = config('media.carousel_disk', 'public');
        abort_unless(in_array($disk, ['public', 'r2_public'], true), 500);

        return $disk;
    }

    private function payload(CatalogSlide $slide): array
    {
        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'subtitle' => $slide->subtitle,
            'button_text' => $slide->button_text,
            'destination' => $slide->destination,
            'sort_order' => $slide->sort_order,
            'active' => $slide->active,
            'desktop_url' => $slide->desktop_url,
            'mobile_url' => $slide->mobile_url,
            'has_mobile_image' => (bool) $slide->mobile_path,
        ];
    }
}
