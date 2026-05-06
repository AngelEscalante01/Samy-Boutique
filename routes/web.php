<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\LayawayController;
use App\Http\Controllers\LayawayPrintController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CashCutsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\SalesPrintController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\SyncSalesController;
use App\Http\Controllers\OfflineSnapshotController;
use App\Http\Controllers\Catalogs\CategoryController;
use App\Http\Controllers\Catalogs\SizeController;
use App\Http\Controllers\Catalogs\ColorController;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/catalogo', [PublicCatalogController::class, 'index'])
    ->name('public.catalog.index');

Route::get('/catalogo/p/{product:sku}', [PublicCatalogController::class, 'show'])
    ->name('public.catalog.show');

Route::get('/offline', [PublicCatalogController::class, 'offline'])
    ->name('public.offline');

Route::get('/storage/{path}', function (string $path) {
    $normalized = ltrim(str_replace('\\\\', '/', $path), '/');

    abort_if($normalized === '' || str_contains($normalized, '..'), 404);

    $disk = Storage::disk('public');

    if (! $disk->exists($normalized)) {
        abort(404);
    }

    $absolutePath = $disk->path($normalized);
    return response()->file($absolutePath, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where('path', '.*')->name('storage.fallback');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::prefix('dashboard/data')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary'])
            ->name('dashboard.data.summary');

        Route::get('/chart', [DashboardController::class, 'chart'])
            ->name('dashboard.data.chart');

        Route::get('/recent-sales', [DashboardController::class, 'recentSales'])
            ->name('dashboard.data.recent-sales');

        Route::get('/recent-layaways', [DashboardController::class, 'recentLayaways'])
            ->name('dashboard.data.recent-layaways');

        Route::get('/payment-summary', [DashboardController::class, 'paymentSummary'])
            ->name('dashboard.data.payment-summary');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Ejemplos de protección con Spatie (roles/permisos)
    Route::get('/pos', [PosController::class, 'index'])
        ->middleware('permission:pos.view')
        ->name('pos.index');

    Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])
        ->middleware('permission:pos.view')
        ->name('pos.customers.search');

    // Usuarios (solo gerente)
    Route::get('/users', [UserController::class, 'index'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.index');

    Route::get('/users/create', [UserController::class, 'create'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.create');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.store');

    Route::get('/users/{user}/edit', [UserController::class, 'edit'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.edit');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.update');

    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.toggleActive');

    Route::patch('/users/{user}/password', [UserController::class, 'updatePassword'])
        ->middleware(['role:gerente', 'permission:users.manage'])
        ->name('users.updatePassword');

    // Reportes
    Route::get('/reports', [ReportsController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');

    // Reportes: corte diario
    Route::get('/reports/daily-cut', [ReportsController::class, 'dailyCut'])
        ->middleware('permission:reports.view')
        ->name('reports.dailyCut');

    Route::post('/reports/daily-cut/preview', [ReportsController::class, 'previewDailyCut'])
        ->middleware('permission:reports.view')
        ->name('reports.dailyCut.preview');

    Route::post('/reports/daily-cut/save', [ReportsController::class, 'saveDailyCut'])
        ->middleware('permission:reports.view')
        ->name('reports.dailyCut.save');

    // Inventario / Productos
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');

    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');

    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');

    // Productos (endpoints)
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.update')
        ->name('products.update');

    Route::delete('/products/{product}/images/{productImage}', [ProductController::class, 'destroyImage'])
        ->middleware(['role:gerente', 'permission:products.delete_images'])
        ->name('products.images.destroy');

    // Ventas - historial
    Route::get('/sales', [SalesController::class, 'index'])
        ->middleware('permission:sales.view')
        ->name('sales.index');

    Route::get('/sales/movements/{type}/{id}', [SalesController::class, 'movementShow'])
        ->middleware('permission:sales.view')
        ->name('sales.movements.show');

    Route::get('/sales/{sale}', [SalesController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.show');

    Route::get('/sales/{sale}/print-data', [SalesPrintController::class, 'show'])
        ->middleware('permission:sales.view')
        ->name('sales.print-data');

    Route::post('/sales/{sale}/print-audit', [SalesPrintController::class, 'storeAudit'])
        ->middleware('permission:sales.view')
        ->name('sales.print-audit.store');

    Route::patch('/sales/{sale}/cancel', [SalesController::class, 'cancel'])
        ->middleware('permission:sales.cancel')
        ->name('sales.cancel');

        // Clientes
        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware('permission:customers.view')
            ->name('customers.index');

        Route::get('/customers/create', [CustomerController::class, 'create'])
            ->middleware('permission:customers.create')
            ->name('customers.create');

        Route::post('/customers', [CustomerController::class, 'store'])
            ->middleware('permission:customers.create')
            ->name('customers.store');

        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:customers.view')
            ->name('customers.show');

        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->middleware('permission:customers.update')
            ->name('customers.edit');

        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('permission:customers.update')
            ->name('customers.update');

        // Cupones
        Route::get('/coupons', [CouponController::class, 'index'])
            ->middleware('permission:coupons.manage')
            ->name('coupons.index');

        Route::get('/coupons/create', [CouponController::class, 'create'])
            ->middleware('permission:coupons.manage')
            ->name('coupons.create');

        Route::post('/coupons', [CouponController::class, 'store'])
            ->middleware('permission:coupons.manage')
            ->name('coupons.store');

        Route::get('/coupons/{coupon}/edit', [CouponController::class, 'edit'])
            ->middleware('permission:coupons.manage')
            ->name('coupons.edit');

        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])
            ->middleware('permission:coupons.manage')
            ->name('coupons.update');

        // Catálogos
        Route::get('/catalogs', function () {
            return redirect()->route('catalogs.categories.index');
        })
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.index');

        Route::get('/catalogs/categories', [CategoryController::class, 'index'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.categories.index');

        Route::post('/catalogs/categories', [CategoryController::class, 'store'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.categories.store');

        Route::put('/catalogs/categories/{category}', [CategoryController::class, 'update'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.categories.update');

        Route::patch('/catalogs/categories/{category}/toggle', [CategoryController::class, 'toggle'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.categories.toggle');

        Route::delete('/catalogs/categories/{category}', [CategoryController::class, 'destroy'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.categories.destroy');

        Route::get('/catalogs/sizes', [SizeController::class, 'index'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.sizes.index');

        Route::post('/catalogs/sizes', [SizeController::class, 'store'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.sizes.store');

        Route::put('/catalogs/sizes/{size}', [SizeController::class, 'update'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.sizes.update');

        Route::patch('/catalogs/sizes/{size}/toggle', [SizeController::class, 'toggle'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.sizes.toggle');

        Route::delete('/catalogs/sizes/{size}', [SizeController::class, 'destroy'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.sizes.destroy');

        Route::get('/catalogs/colors', [ColorController::class, 'index'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.colors.index');

        Route::post('/catalogs/colors', [ColorController::class, 'store'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.colors.store');

        Route::put('/catalogs/colors/{color}', [ColorController::class, 'update'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.colors.update');

        Route::patch('/catalogs/colors/{color}/toggle', [ColorController::class, 'toggle'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.colors.toggle');

        Route::delete('/catalogs/colors/{color}', [ColorController::class, 'destroy'])
            ->middleware('permission:catalogs.manage')
            ->name('catalogs.colors.destroy');

        // Configuración
        Route::get('/settings', [SettingsController::class, 'index'])
            ->middleware(['role:gerente', 'permission:settings.manage'])
            ->name('settings.index');

        Route::put('/settings', [SettingsController::class, 'update'])
            ->middleware(['role:gerente', 'permission:settings.manage'])
            ->name('settings.update');

        // Notificaciones por correo (solo gerente)
        Route::get('/notifications', [NotificationSettingsController::class, 'index'])
            ->middleware(['role:gerente'])
            ->name('notifications.index');

        Route::post('/notifications/emails', [NotificationSettingsController::class, 'storeEmail'])
            ->middleware(['role:gerente'])
            ->name('notifications.emails.store');

        Route::patch('/notifications/emails/{email}/toggle', [NotificationSettingsController::class, 'toggleEmail'])
            ->middleware(['role:gerente'])
            ->name('notifications.emails.toggle');

        Route::delete('/notifications/emails/{email}', [NotificationSettingsController::class, 'destroyEmail'])
            ->middleware(['role:gerente'])
            ->name('notifications.emails.destroy');

        Route::put('/notifications/settings', [NotificationSettingsController::class, 'updateSettings'])
            ->middleware(['role:gerente'])
            ->name('notifications.settings.update');

    // Temporal: ejecutar migraciones desde web en producción sin terminal.
    Route::get('/_ops/temp/run-migrations', function (Request $request) {
        abort_unless((bool) config('samy.temp_migration_route_enabled', false), 404);

        $expectedToken = trim((string) config('samy.temp_migration_route_token', ''));
        $providedToken = trim((string) $request->query('token', ''));

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403, 'Token invalido.');
        }

        @set_time_limit(0);

        try {
            Artisan::call('migrate', ['--force' => true]);

            return response()->json([
                'ok' => true,
                'message' => 'Migraciones ejecutadas correctamente.',
                'output' => trim(Artisan::output()),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => 'No fue posible ejecutar migraciones.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    })
        ->middleware(['role:gerente', 'permission:settings.manage'])
        ->name('ops.temp.run-migrations');

    Route::post('/sales', [SalesController::class, 'store'])
        ->middleware('permission:sales.create')
        ->name('sales.store');

    Route::post('/sales/preview', [SalesController::class, 'preview'])
        ->middleware('permission:sales.create')
        ->name('sales.preview');

    Route::post('/sync/sales', [SyncSalesController::class, 'store'])
        ->middleware('permission:sales.create')
        ->name('sync.sales.store');

    Route::get('/offline/snapshot/meta', [OfflineSnapshotController::class, 'meta'])
        ->middleware('permission:pos.view')
        ->name('offline.snapshot.meta');

    Route::get('/offline/snapshot', [OfflineSnapshotController::class, 'snapshot'])
        ->middleware('permission:pos.view')
        ->name('offline.snapshot');

    Route::get('/sync/pending', fn () => Inertia::render('Sync/Index'))
        ->middleware(['role:gerente|cajero', 'permission:pos.view'])
        ->name('sync.index');

    // Cortes de caja
    Route::get('/cash-cuts', [CashCutsController::class, 'index'])
        ->middleware(['role:gerente', 'permission:reports.view'])
        ->name('cashcuts.index');

    Route::post('/cash-cuts/preview', [CashCutsController::class, 'preview'])
        ->middleware(['role:gerente', 'permission:reports.view'])
        ->name('cashcuts.preview');

    Route::post('/cash-cuts', [CashCutsController::class, 'store'])
        ->middleware(['role:gerente', 'permission:reports.view'])
        ->name('cashcuts.store');

    Route::get('/cash-cuts/{cashCut}', [CashCutsController::class, 'show'])
        ->middleware(['role:gerente', 'permission:reports.view'])
        ->name('cashcuts.show');

    // Apartados
    Route::get('/layaways', [LayawayController::class, 'index'])
        ->middleware('permission:pos.view')
        ->name('layaways.index');

    Route::get('/layaways/create', [LayawayController::class, 'create'])
        ->middleware('permission:pos.view')
        ->name('layaways.create');

    Route::get('/layaways/{layaway}', [LayawayController::class, 'show'])
        ->middleware('permission:pos.view')
        ->name('layaways.show');

    Route::post('/layaways', [LayawayController::class, 'store'])
        ->middleware('permission:pos.view')
        ->name('layaways.store');

    Route::post('/layaways/{layaway}/payments', [LayawayController::class, 'addPayment'])
        ->middleware('permission:pos.view')
        ->name('layaways.payments.store');

    Route::patch('/layaways/{layaway}/vigencia', [LayawayController::class, 'updateVigencia'])
        ->middleware('permission:pos.view')
        ->name('layaways.vigencia.update');

    Route::get('/layaways/{layaway}/print-data/created', [LayawayPrintController::class, 'created'])
        ->middleware('permission:pos.view')
        ->name('layaways.print.created');

    Route::get('/layaways/{layaway}/print-data', [LayawayPrintController::class, 'created'])
        ->middleware('permission:pos.view')
        ->name('layaways.print-data');

    Route::get('/layaways/{layaway}/payments/{payment}/print-data', [LayawayPrintController::class, 'payment'])
        ->middleware('permission:pos.view')
        ->name('layaways.print.payment');

    Route::get('/layaways/{layaway}/print-data/closed', [LayawayPrintController::class, 'closed'])
        ->middleware('permission:pos.view')
        ->name('layaways.print.closed');

    Route::get('/layaways/{layaway}/close-print-data', [LayawayPrintController::class, 'closed'])
        ->middleware('permission:pos.view')
        ->name('layaways.close-print-data');

    Route::post('/layaways/{layaway}/liquidate', [LayawayController::class, 'liquidate'])
        ->middleware('permission:pos.view')
        ->name('layaways.liquidate');

    Route::post('/layaways/{layaway}/cancel', [LayawayController::class, 'cancel'])
        ->middleware('permission:pos.view')
        ->name('layaways.cancel');
});

require __DIR__.'/auth.php';
