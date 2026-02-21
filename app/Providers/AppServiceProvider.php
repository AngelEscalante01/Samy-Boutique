<?php

namespace App\Providers;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
