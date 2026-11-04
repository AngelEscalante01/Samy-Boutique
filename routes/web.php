<?php

use App\Http\Controllers\CashCutsController;
use App\Http\Controllers\CatalogSlideController;
use App\Http\Controllers\Catalogs\CategoryController;
use App\Http\Controllers\Catalogs\ColorController;
use App\Http\Controllers\Catalogs\OccasionController;
use App\Http\Controllers\Catalogs\SizeController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LayawayController;
use App\Http\Controllers\LayawayPrintController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\OfflineSnapshotController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductBulkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SalesPrintController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SyncSalesController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', [PublicCatalogController::class, 'home'])->name('public.catalog.home');

Route::get('/robots.txt', [PublicCatalogController::class, 'robots'])->name('public.robots');
Route::get('/sitemap.xml', [PublicCatalogController::class, 'sitemap'])->name('public.sitemap');

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

Route::get('/firebase-messaging-sw.js', function () {
    $web = config('firebase.web', []);
    $config = json_encode([
        'apiKey' => $web['api_key'] ?? '',
        'authDomain' => $web['auth_domain'] ?? '',
        'projectId' => $web['project_id'] ?? '',
        'storageBucket' => $web['storage_bucket'] ?? '',
        'messagingSenderId' => $web['messaging_sender_id'] ?? '',
        'appId' => $web['app_id'] ?? '',
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    $version = preg_replace('/[^0-9.]/', '', (string) config('firebase.js_sdk_version', '12.19.0'));

    return response("self.__samyFirebaseMessagingInitialized = false;\n"
        ."self.addEventListener('notificationclick', (event) => {\n"
        ."  const data = event.notification?.data || {};\n"
        ."  const rawId = data.FCM_MSG?.data?.notification_id || data.notification_id || data.FCM_MSG?.notification?.data?.notification_id || '';\n"
        ."  const id = /^\\d+$/.test(String(rawId)) ? String(rawId) : '';\n"
        ."  const path = id ? '/notifications?notification_id=' + encodeURIComponent(id) : '/notifications';\n"
        ."  event.stopImmediatePropagation();\n"
        ."  event.notification.close();\n"
        ."  event.waitUntil((async () => {\n"
        ."    const target = new URL(path, self.location.origin).href;\n"
        ."    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });\n"
        ."    const existing = windows.find((client) => new URL(client.url).origin === self.location.origin);\n"
        ."    if (existing) { const navigated = await existing.navigate(target); await (navigated || existing).focus(); }\n"
        ."    else await self.clients.openWindow(target);\n"
        ."  })());\n"
        ."});\n"
        ."self.addEventListener('message', (event) => {\n"
        ."  if (event.data?.type !== 'SAMY_FCM_DIAGNOSTICS' || !event.ports?.[0]) return;\n"
        ."  event.ports[0].postMessage({\n"
        ."    messagingInitialized: self.__samyFirebaseMessagingInitialized === true,\n"
        ."    messagingUnavailable: self.__samyFirebaseMessagingUnavailable === true,\n"
        ."  });\n"
        ."});\n"
        ."try {\n"
        ."  importScripts('https://www.gstatic.com/firebasejs/{$version}/firebase-app-compat.js');\n"
        ."  importScripts('https://www.gstatic.com/firebasejs/{$version}/firebase-messaging-compat.js');\n"
        ."  firebase.initializeApp({$config});\n"
        ."  firebase.messaging().onBackgroundMessage(() => {\n"
        ."    self.__samyBackgroundMessageCount = (self.__samyBackgroundMessageCount || 0) + 1;\n"
        ."  });\n"
        ."  self.__samyFirebaseMessagingInitialized = true;\n"
        ."  console.info('[Samy FCM SW] Firebase Messaging initialized.');\n"
        ."} catch (error) {\n"
        ."  self.__samyFirebaseMessagingUnavailable = true;\n"
        ."  console.error('[Samy FCM SW] Firebase Messaging initialization failed.', error && error.name ? error.name : 'Error');\n"
        .'}', 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
})->name('firebase.messaging-sw');

Route::get('/sw.js', function () {
    $workerPath = public_path('build/sw.js');
    $version = substr(hash('sha256',
        (is_file($workerPath) ? hash_file('sha256', $workerPath) : 'missing')
        .json_encode(config('firebase.web', []))
        .config('firebase.js_sdk_version')
        .filemtime(__FILE__)), 0, 12);

    return response("self.__samyWorkerVersion = '{$version}';\n"
        ."self.addEventListener('message', (event) => {\n"
        ."  if (event.data?.type !== 'SAMY_FCM_PING' || !event.ports?.[0]) return;\n"
        ."  event.ports[0].postMessage({ type: 'SAMY_FCM_PONG', version: self.__samyWorkerVersion, messagingInitialized: self.__samyFirebaseMessagingInitialized === true, messagingUnavailable: self.__samyFirebaseMessagingUnavailable === true, importError: self.__samyWorkerImportError || null });\n"
        ."});\n"
        ."try { importScripts('/build/sw.js?v={$version}'); } catch (error) {\n"
        ."  self.__samyWorkerImportError = error?.name || 'Error';\n"
        ."  console.error('[Samy PWA] Workbox import failed.', self.__samyWorkerImportError);\n"
        .'}', 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Service-Worker-Allowed' => '/',
        ]);
})->name('pwa.service-worker');

Route::get('/manifest.webmanifest', function () {
    $manifest = json_decode(file_get_contents(public_path('build/manifest.webmanifest')), true);
    $systemHost = parse_url((string) config('storefront.system_url'), PHP_URL_HOST);
    $manifest['start_url'] = request()->getHost() === $systemHost ? '/login' : '/';
    return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
})->name('pwa.manifest');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::prefix('dashboard/data')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/summary', [DashboardController::class, 'summary'])->name('dashboard.data.summary');
    Route::get('/chart', [DashboardController::class, 'chart'])->name('dashboard.data.chart');
    Route::get('/recent-sales', [DashboardController::class, 'recentSales'])->name('dashboard.data.recent-sales');
    Route::get('/recent-layaways', [DashboardController::class, 'recentLayaways'])->name('dashboard.data.recent-layaways');
    Route::get('/payment-summary', [DashboardController::class, 'paymentSummary'])->name('dashboard.data.payment-summary');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', fn () => Inertia::render('Notifications/Index', [
        'canSend' => request()->user()->can('notifications.send'),
        'webPushConfigured' => filled(config('firebase.vapid_key')) && filled(config('firebase.web.api_key')),
    ]))->name('notifications.index');
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

    Route::get('/pos/resolve-code', [PosController::class, 'resolveCode'])
        ->middleware(['permission:pos.view', 'permission:sales.create'])
        ->name('pos.resolve-code');

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

    Route::get('/reports/export/{format}', [ReportsController::class, 'export'])
        ->whereIn('format', ['pdf', 'xlsx'])
        ->middleware('permission:reports.view')
        ->name('reports.export');

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

    Route::get('/products/bulk', [ProductBulkController::class, 'index'])
        ->middleware('permission:products.update')->name('products.bulk');

    Route::post('/products/{product}/bulk-images', [ProductBulkController::class, 'storeImages'])
        ->middleware('permission:products.update')->name('products.bulk-images.store');

    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.update')
        ->name('products.edit');

    Route::get('/products/{product}', [ProductController::class, 'show'])
        ->whereNumber('product')->middleware('permission:products.view')->name('products.show');

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

    Route::put('/products/{product}/images/order', [ProductController::class, 'reorderImages'])
        ->middleware('permission:products.update')->name('products.images.order');
    Route::put('/products/{product}/images/{productImage}/variant', [ProductController::class, 'assignImageVariant'])
        ->middleware('permission:products.update')->name('products.images.variant');

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

    Route::get('/sales/{sale}/ticket', [\App\Http\Controllers\WebTicketController::class, 'sale'])
        ->middleware('permission:sales.view')->name('sales.ticket');

    Route::get('/layaways/{layaway}/ticket/{kind?}', [\App\Http\Controllers\WebTicketController::class, 'layaway'])
        ->whereIn('kind', ['created', 'closed'])->middleware('permission:layaways.view')->name('layaways.ticket');

    Route::get('/layaways/{layaway}/payments/{payment}/ticket', [\App\Http\Controllers\WebTicketController::class, 'payment'])
        ->middleware('permission:layaways.view')->name('layaways.payments.ticket');

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

    Route::get('/customers/{customer}/photo', [CustomerController::class, 'photo'])
        ->middleware('permission:customers.view')->name('customers.photo');
    Route::post('/customers/{customer}/photo', [CustomerController::class, 'uploadPhoto'])
        ->middleware('permission:customers.update')->name('customers.photo.store');
    Route::delete('/customers/{customer}/photo', [CustomerController::class, 'deletePhoto'])
        ->middleware('permission:customers.update')->name('customers.photo.destroy');

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

    Route::get('/catalog-slides', [CatalogSlideController::class, 'index'])
        ->middleware('permission:catalogs.manage')->name('catalog-slides.index');
    Route::post('/catalog-slides', [CatalogSlideController::class, 'store'])
        ->middleware('permission:catalogs.manage')->name('catalog-slides.store');
    Route::put('/catalog-slides/{slide}', [CatalogSlideController::class, 'update'])
        ->middleware('permission:catalogs.manage')->name('catalog-slides.update');
    Route::delete('/catalog-slides/{slide}', [CatalogSlideController::class, 'destroy'])
        ->middleware('permission:catalogs.manage')->name('catalog-slides.destroy');

    Route::get('/catalogs/occasions', [OccasionController::class, 'index'])
        ->middleware('permission:catalogs.manage')->name('catalogs.occasions.index');
    Route::post('/catalogs/occasions', [OccasionController::class, 'store'])
        ->middleware('permission:catalogs.manage')->name('catalogs.occasions.store');
    Route::put('/catalogs/occasions/{occasion}', [OccasionController::class, 'update'])
        ->middleware('permission:catalogs.manage')->name('catalogs.occasions.update');
    Route::post('/catalogs/occasions/assign', [OccasionController::class, 'assign'])
        ->middleware('permission:products.update')->name('catalogs.occasions.assign');

    // Configuración
    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware(['role:gerente', 'permission:settings.manage'])
        ->name('settings.index');

    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware(['role:gerente', 'permission:settings.manage'])
        ->name('settings.update');

    Route::get('/notifications/email-settings', [NotificationSettingsController::class, 'index'])
        ->middleware('role:gerente')
        ->name('notifications.email-settings');

    Route::post('/notifications/emails', [NotificationSettingsController::class, 'storeEmail'])
        ->middleware('role:gerente')
        ->name('notifications.emails.store');

    Route::patch('/notifications/emails/{email}/toggle', [NotificationSettingsController::class, 'toggleEmail'])
        ->middleware('role:gerente')
        ->name('notifications.emails.toggle');

    Route::delete('/notifications/emails/{email}', [NotificationSettingsController::class, 'destroyEmail'])
        ->middleware('role:gerente')
        ->name('notifications.emails.destroy');

    Route::put('/notifications/settings', [NotificationSettingsController::class, 'updateSettings'])
        ->middleware('role:gerente')
        ->name('notifications.settings.update');

    // Mantener la ruta operativa existente de producción, desactivada por defecto.
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
        ->middleware(['permission:sales.create', \App\Http\Middleware\IdempotentApiOperation::class])
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
        ->middleware('permission:layaways.view')
        ->name('layaways.index');

    Route::get('/layaways/create', [LayawayController::class, 'create'])
        ->middleware('permission:layaways.create')
        ->name('layaways.create');

    Route::get('/layaways/{layaway}/edit', [LayawayController::class, 'edit'])
        ->middleware('permission:layaways.create')->name('layaways.edit');
    Route::post('/layaways/{layaway}/adjustment-preview', [LayawayController::class, 'previewAdjustment'])
        ->middleware('permission:layaways.create')->name('layaways.adjustment.preview');
    Route::put('/layaways/{layaway}/adjustment', [LayawayController::class, 'updateAdjustment'])
        ->middleware('permission:layaways.create')->name('layaways.adjustment.update');
    Route::post('/layaways/{layaway}/refunds', [LayawayController::class, 'refund'])
        ->middleware(['role:gerente', 'permission:layaways.cancel'])->name('layaways.refunds.store');
    Route::post('/layaways/{layaway}/deliver', [LayawayController::class, 'deliver'])
        ->middleware(['role:gerente', 'permission:layaways.cancel'])->name('layaways.deliver');

    Route::get('/layaways/{layaway}', [LayawayController::class, 'show'])
        ->middleware('permission:layaways.view')
        ->name('layaways.show');

    Route::post('/layaways', [LayawayController::class, 'store'])
        ->middleware('permission:layaways.create')
        ->name('layaways.store');

    Route::post('/layaways/{layaway}/payments', [LayawayController::class, 'addPayment'])
        ->middleware('permission:layaways.pay')
        ->name('layaways.payments.store');

    Route::patch('/layaways/{layaway}/vigencia', [LayawayController::class, 'updateVigencia'])
        ->middleware('permission:layaways.create')
        ->name('layaways.vigencia.update');

    Route::get('/layaways/{layaway}/print-data/created', [LayawayPrintController::class, 'created'])
        ->middleware('permission:layaways.view')
        ->name('layaways.print.created');

    Route::get('/layaways/{layaway}/print-data', [LayawayPrintController::class, 'created'])
        ->middleware('permission:layaways.view')
        ->name('layaways.print-data');

    Route::get('/layaways/{layaway}/payments/{payment}/print-data', [LayawayPrintController::class, 'payment'])
        ->middleware('permission:layaways.view')
        ->name('layaways.print.payment');

    Route::get('/layaways/{layaway}/print-data/closed', [LayawayPrintController::class, 'closed'])
        ->middleware('permission:layaways.view')
        ->name('layaways.print.closed');

    Route::get('/layaways/{layaway}/close-print-data', [LayawayPrintController::class, 'closed'])
        ->middleware('permission:layaways.view')
        ->name('layaways.close-print-data');

    Route::post('/layaways/{layaway}/liquidate', [LayawayController::class, 'liquidate'])
        ->middleware('permission:layaways.pay')
        ->name('layaways.liquidate');

    Route::post('/layaways/{layaway}/cancel', [LayawayController::class, 'cancel'])
        ->middleware('permission:layaways.cancel')
        ->name('layaways.cancel');
});

require __DIR__.'/auth.php';

require __DIR__.'/catalog_imports.php';

require __DIR__.'/product_labels.php';
