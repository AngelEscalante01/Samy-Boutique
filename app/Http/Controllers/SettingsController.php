<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'loyalty' => [
                    'enabled' => (bool) Setting::get('loyalty.enabled', false),
                    'type' => (string) Setting::get('loyalty.type', 'percent'),
                    'value' => (float) Setting::get('loyalty.value', 0),
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'loyalty.enabled' => ['required', 'boolean'],
            'loyalty.type' => ['required', 'string', 'in:amount,percent'],
            'loyalty.value' => ['required', 'numeric', 'min:0'],
        ]);

        $loyalty = $validated['loyalty'] ?? [];

        Setting::set('loyalty.enabled', (bool) ($loyalty['enabled'] ?? false));
        Setting::set('loyalty.type', (string) ($loyalty['type'] ?? 'percent'));
        Setting::set('loyalty.value', (float) ($loyalty['value'] ?? 0));

        return back()->with('success', 'Configuración guardada.');
    }
}
