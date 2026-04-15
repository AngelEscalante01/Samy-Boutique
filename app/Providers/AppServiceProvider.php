<?php

namespace App\Providers;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix para MySQL < 5.7.7 / MariaDB que no soportan índices largos con utf8mb4
        Schema::defaultStringLength(191);
        RateLimiter::for('api-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', 'guest'));
            $key = sprintf('%s|%s', $email, (string) $request->ip());

            return Limit::perMinute(5)
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Demasiados intentos de inicio de sesion.',
                        'data' => null,
                        'errors' => [
                            'auth' => ['Intenta nuevamente en 1 minuto.'],
                        ],
                    ], 429);
                });
        });

        Gate::define('sales.cancel.sale', function (User $user, Sale $sale): bool {
            if (! $user->can('sales.cancel')) {
                return false;
            }

            if ($user->hasRole('gerente')) {
                return true;
            }

            if ($user->hasRole('cajero')) {
                return (int) $sale->created_by === (int) $user->id;
            }

            return false;
        });

        Vite::prefetch(concurrency: 3);
    }
}
