<?php

use App\Http\Controllers as C;
use App\Http\Middleware\{EnsureActiveApiUser, IdempotentApiOperation, NativeApiResponse};
use Illuminate\Support\Facades\Route;

// Explicit permissions mirror web routes; no HTML or browser session is exposed.
Route::prefix('mobile')->middleware(['auth:sanctum', EnsureActiveApiUser::class])->group(function () {
    Route::patch('/profile', [C\ProfileController::class, 'update']);
    Route::put('/profile/password', [C\Auth\PasswordController::class, 'update']);
    Route::get('/notifications/recipients', [C\Api\NotificationController::class, 'recipients'])->middleware('can:notifications.send');
    Route::get('/notifications/sent-history', [C\Api\NotificationController::class, 'sentHistory'])->middleware('can:notifications.send');
    Route::post('/notifications/manual', [C\Api\NotificationController::class, 'sendManual'])->middleware(['can:notifications.send', 'throttle:5,1']);
    Route::get('/dashboard', [C\DashboardController::class, 'index'])->middleware('role:gerente');
    Route::get('/product-labels', [C\ProductLabelController::class, 'index'])->middleware('permission:products.view');
    foreach (['products' => C\ProductController::class, 'customers' => C\CustomerController::class,
        'coupons' => C\CouponController::class, 'users' => C\UserController::class] as $kind => $controller) {
        $parameter = ['products' => 'product', 'customers' => 'customer', 'coupons' => 'coupon', 'users' => 'user'][$kind];
        $manager = $kind === 'users' ? ['role:gerente'] : [];
        foreach (['index' => 'view', 'create' => 'create', 'store' => 'create', 'edit' => 'update', 'update' => 'update'] as $action => $ability) {
            $permission = in_array($kind, ['coupons', 'users']) ? "$kind.manage" : "$kind.$ability";
            $path = match ($action) { 'create' => "/$kind/create", 'edit' => "/$kind/{{$parameter}}/edit", 'update' => "/$kind/{{$parameter}}", default => "/$kind" };
            $method = match ($action) { 'store' => 'post', 'update' => 'put', default => 'get' };
            Route::$method($path, [$controller, $action])->middleware([...$manager, "permission:$permission"]);
        }
    }
    Route::get('/products/bulk', [C\ProductBulkController::class, 'index'])->middleware('permission:products.update');
    Route::get('/products/{product}', [C\Api\NativeProductController::class, 'show'])->middleware('permission:products.view');
    Route::post('/products/{product}/bulk-images', [C\ProductBulkController::class, 'storeImages'])->middleware('permission:products.update');
    Route::delete('/products/{product}/images/{productImage}', [C\ProductController::class, 'destroyImage'])->middleware(['role:gerente', 'permission:products.delete_images']);
    Route::put('/products/{product}/images/order', [C\ProductController::class, 'reorderImages'])->middleware('permission:products.update');
    Route::put('/products/{product}/images/{productImage}/variant', [C\ProductController::class, 'assignImageVariant'])->middleware('permission:products.update');
    Route::get('/customers/{customer}/photo', [C\CustomerController::class, 'photo'])->middleware('permission:customers.view');
    Route::post('/customers/{customer}/photo', [C\CustomerController::class, 'uploadPhoto'])->middleware('permission:customers.update');
    Route::delete('/customers/{customer}/photo', [C\CustomerController::class, 'deletePhoto'])->middleware('permission:customers.update');
    Route::get('/customers/{customer}', [C\CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::patch('/users/{user}/toggle-active', [C\UserController::class, 'toggleActive'])->middleware(['role:gerente', 'permission:users.manage']);
    Route::patch('/users/{user}/password', [C\UserController::class, 'updatePassword'])->middleware(['role:gerente', 'permission:users.manage']);
    foreach (['categories' => C\Catalogs\CategoryController::class, 'sizes' => C\Catalogs\SizeController::class,
        'colors' => C\Catalogs\ColorController::class, 'occasions' => C\Catalogs\OccasionController::class] as $kind => $controller) {
        $parameter = ['categories' => 'category', 'sizes' => 'size', 'colors' => 'color', 'occasions' => 'occasion'][$kind];
        Route::get("/catalogs/$kind", [$controller, 'index'])->middleware('permission:catalogs.manage');
        Route::post("/catalogs/$kind", [$controller, 'store'])->middleware('permission:catalogs.manage');
        Route::put("/catalogs/$kind/{{$parameter}}", [$controller, 'update'])->middleware('permission:catalogs.manage');
        if ($kind !== 'occasions') {
            Route::patch("/catalogs/$kind/{{$parameter}}/toggle", [$controller, 'toggle'])->middleware('permission:catalogs.manage');
            Route::delete("/catalogs/$kind/{{$parameter}}", [$controller, 'destroy'])->middleware('permission:catalogs.manage');
        }
    }
    Route::post('/catalogs/occasions/assign', [C\Catalogs\OccasionController::class, 'assign'])->middleware('permission:products.update');
    Route::get('/catalog-slides', [C\CatalogSlideController::class, 'index'])->middleware('permission:catalogs.manage');
    Route::post('/catalog-slides', [C\CatalogSlideController::class, 'store'])->middleware('permission:catalogs.manage');
    Route::put('/catalog-slides/{slide}', [C\CatalogSlideController::class, 'update'])->middleware('permission:catalogs.manage');
    Route::delete('/catalog-slides/{slide}', [C\CatalogSlideController::class, 'destroy'])->middleware('permission:catalogs.manage');
    Route::get('/layaways', [C\LayawayController::class, 'index'])->middleware('permission:layaways.view');
    Route::get('/layaways/create', [C\LayawayController::class, 'create'])->middleware('permission:layaways.create');
    Route::post('/layaways', [C\LayawayController::class, 'store'])->middleware('permission:layaways.create');
    Route::post('/layaways/{layaway}/payments', [C\Api\BusinessApiController::class, 'addLayawayPayment'])->middleware('permission:layaways.pay');
    Route::get('/layaways/{layaway}', [C\LayawayController::class, 'show'])->middleware('permission:layaways.view');
    Route::get('/layaways/{layaway}/edit', [C\LayawayController::class, 'edit'])->middleware('permission:layaways.create');
    Route::post('/layaways/{layaway}/adjustment-preview', [C\LayawayController::class, 'previewAdjustment'])->middleware('permission:layaways.create');
    Route::put('/layaways/{layaway}/adjustment', [C\LayawayController::class, 'updateAdjustment'])->middleware('permission:layaways.create');
    Route::post('/layaways/{layaway}/refunds', [C\LayawayController::class, 'refund'])->middleware(['role:gerente', 'permission:layaways.cancel']);
    Route::post('/layaways/{layaway}/deliver', [C\LayawayController::class, 'deliver'])->middleware(['role:gerente', 'permission:layaways.cancel']);
    Route::post('/layaways/{layaway}/liquidate', [C\LayawayController::class, 'liquidate'])->middleware('permission:layaways.pay');
    Route::post('/layaways/{layaway}/cancel', [C\LayawayController::class, 'cancel'])->middleware('permission:layaways.cancel');
    Route::patch('/layaways/{layaway}/vigencia', [C\LayawayController::class, 'updateVigencia'])->middleware('permission:layaways.create');
    Route::get('/sales', [C\SalesController::class, 'index'])->middleware('permission:sales.view');
    Route::get('/sales/{sale}', [C\SalesController::class, 'show'])->middleware('permission:sales.view');
    Route::get('/sales/movements/{type}/{id}', [C\SalesController::class, 'movementShow'])->middleware('permission:sales.view');
    Route::patch('/sales/{sale}/cancel', [C\SalesController::class, 'cancel'])->middleware('permission:sales.cancel');
    Route::get('/reports', [C\ReportsController::class, 'index'])->middleware('permission:reports.view');
    Route::get('/reports/export/{format}', [C\ReportsController::class, 'export'])->whereIn('format', ['pdf', 'xlsx'])->middleware('permission:reports.view');
    Route::get('/cash-cuts', [C\CashCutsController::class, 'index'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::get('/cash-cuts/{cashCut}', [C\CashCutsController::class, 'show'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::post('/cash-cuts/preview', [C\CashCutsController::class, 'preview'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::post('/cash-cuts', [C\CashCutsController::class, 'store'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::get('/settings', [C\SettingsController::class, 'index'])->middleware(['role:gerente', 'permission:settings.manage']);
    Route::put('/settings', [C\SettingsController::class, 'update'])->middleware(['role:gerente', 'permission:settings.manage']);
    Route::get('/notifications/email-settings', [C\NotificationSettingsController::class, 'index'])->middleware('role:gerente');
    Route::post('/notifications/emails', [C\NotificationSettingsController::class, 'storeEmail'])->middleware('role:gerente');
    Route::patch('/notifications/emails/{email}/toggle', [C\NotificationSettingsController::class, 'toggleEmail'])->middleware('role:gerente');
    Route::delete('/notifications/emails/{email}', [C\NotificationSettingsController::class, 'destroyEmail'])->middleware('role:gerente');
    Route::put('/notifications/settings', [C\NotificationSettingsController::class, 'updateSettings'])->middleware('role:gerente');
    foreach (['index' => '', 'template' => '/template', 'exportUpdate' => '/export-update', 'preview' => '/preview', 'show' => '/{batch}', 'confirm' => '/{batch}/confirm'] as $action => $path) {
        $method = in_array($action, ['preview', 'confirm']) ? 'post' : 'get';
        Route::$method('/catalog-imports'.$path, [C\CatalogImportController::class, $action])->middleware('permission:products.create');
    }
});

// Authorize every request, including receipt replays, before reading stored results.
// Convert controller redirects before the idempotency transaction commits.
foreach (Route::getRoutes() as $nativeRoute) {
    if (str_starts_with($nativeRoute->uri(), "api/mobile/")) {
        $nativeRoute->middleware([IdempotentApiOperation::class, NativeApiResponse::class]);
    }
}
