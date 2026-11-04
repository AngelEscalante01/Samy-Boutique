<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\NotificationDelivery;
use App\Models\NotificationDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // The session token rotates at login. A full Inertia navigation refreshes
        // the page's CSRF meta tag before POS and report fetch requests run.
        return Inertia::location(redirect()->intended(route('dashboard', absolute: false)));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $installationId = $request->validate(['installation_id' => ['nullable', 'uuid']])['installation_id'] ?? null;
        if ($installationId) {
            DB::transaction(function () use ($request, $installationId): void {
                $device = NotificationDevice::query()
                    ->where('installation_id', $installationId)
                    ->where('user_id', $request->user()->id)
                    ->lockForUpdate()
                    ->first();
                if (! $device) {
                    return;
                }

                NotificationDelivery::query()
                    ->where('device_id', $device->id)
                    ->whereIn('status', ['pending', 'error_temporal', 'sending'])
                    ->update(['status' => 'cancelled', 'next_attempt_at' => null, 'last_error_code' => 'device_logged_out']);
                $device->update([
                    'user_id' => null,
                    'token' => null,
                    'token_hash' => null,
                    'active' => false,
                    'permission_status' => 'not_determined',
                ]);
            });
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
