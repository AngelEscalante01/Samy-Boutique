<?php

namespace App\Http\Controllers;

use App\Models\NotificationEmail;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Notifications/Index', [
            'emails'   => NotificationEmail::orderByDesc('id')->get(),
            'settings' => [
                'sale_enabled'             => (bool) Setting::get('notifications.sale_enabled', true),
                'layaway_enabled'          => (bool) Setting::get('notifications.layaway_enabled', true),
                'layaway_payment_enabled'  => (bool) Setting::get('notifications.layaway_payment_enabled', true),
            ],
        ]);
    }

    public function storeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:191', 'unique:notification_emails,email'],
            'label' => ['nullable', 'string', 'max:100'],
        ], [
            'email.unique' => 'Este correo ya está registrado.',
        ]);

        NotificationEmail::create([
            'email'  => strtolower(trim($validated['email'])),
            'label'  => $validated['label'] ?? null,
            'active' => true,
        ]);

        return back()->with('success', 'Correo agregado correctamente.');
    }

    public function toggleEmail(NotificationEmail $email): RedirectResponse
    {
        $email->update(['active' => ! $email->active]);

        return back()->with('success', $email->active ? 'Correo activado.' : 'Correo desactivado.');
    }

    public function destroyEmail(NotificationEmail $email): RedirectResponse
    {
        $email->delete();

        return back()->with('success', 'Correo eliminado.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_enabled'            => ['required', 'boolean'],
            'layaway_enabled'         => ['required', 'boolean'],
            'layaway_payment_enabled' => ['required', 'boolean'],
        ]);

        Setting::set('notifications.sale_enabled', (bool) $validated['sale_enabled']);
        Setting::set('notifications.layaway_enabled', (bool) $validated['layaway_enabled']);
        Setting::set('notifications.layaway_payment_enabled', (bool) $validated['layaway_payment_enabled']);

        return back()->with('success', 'Configuración de notificaciones guardada.');
    }
}
