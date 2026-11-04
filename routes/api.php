<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessApiController;
use App\Http\Controllers\LayawayController;
use Illuminate\Support\Facades\Route;

Route::post('/forgot-password', function (\Illuminate\Http\Request $request) {
    $request->validate(['email' => ['required', 'email']]);
    \Illuminate\Support\Facades\Password::sendResetLink($request->only('email'));
    return response()->json(['success' => true, 'message' => 'Si existe la cuenta, recibirás un enlace.']);
})->middleware('throttle:5,1');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureActiveApiUser::class])->group(function () {
    Route::get('/capabilities', fn () => response()->json([
        'success' => true,
        'message' => 'Capacidades del servidor.',
        'data' => [
            'layaways_idempotency_v1' => \Illuminate\Support\Facades\Schema::hasTable('api_operation_receipts'),
            'mobile_operations_v1' => \Illuminate\Support\Facades\Schema::hasTable('api_operation_receipts'),
            'sales_idempotency_v1' => \Illuminate\Support\Facades\Schema::hasTable('api_operation_receipts'),
            'offline_stock_assignments_v1' => false,
        ],
    ]));
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [BusinessApiController::class, 'products'])->middleware('permission:products.view');
    foreach (['categories', 'sizes', 'colors'] as $catalog) {
        Route::get("/catalogs/{$catalog}", [BusinessApiController::class, 'catalog'])->defaults('kind', $catalog)->middleware('permission:catalogs.manage|pos.view');
    }
    Route::get('/customers', [BusinessApiController::class, 'customers'])->middleware('permission:customers.view');
    Route::get('/customers/{customer}', [BusinessApiController::class, 'customer'])->middleware('permission:customers.view');
    Route::post('/customers', [BusinessApiController::class, 'createCustomer'])->middleware('permission:customers.create');
    Route::put('/customers/{customer}', [BusinessApiController::class, 'updateCustomer'])->middleware('permission:customers.update');
    Route::post('/sales/preview', [BusinessApiController::class, 'previewSale'])->middleware('permission:sales.create');
    Route::post('/sales', [BusinessApiController::class, 'storeSale'])->middleware(['permission:sales.create', \App\Http\Middleware\IdempotentApiOperation::class]);
    Route::get('/sales', [BusinessApiController::class, 'sales'])->middleware('permission:sales.view');
    Route::get('/sales/{sale}/ticket', [BusinessApiController::class, 'ticket'])->middleware('permission:sales.view');
    Route::get('/sales/{sale}', [BusinessApiController::class, 'sale'])->middleware('permission:sales.view');
    Route::get('/layaways', [BusinessApiController::class, 'layaways'])->middleware('permission:layaways.view');
    Route::get('/layaways/{layaway}', [BusinessApiController::class, 'layaway'])->middleware('permission:layaways.view');
    Route::post('/layaways', [BusinessApiController::class, 'createLayaway'])->middleware(['permission:layaways.create', \App\Http\Middleware\IdempotentApiOperation::class]);
    Route::post('/layaways/{layaway}/payments', [BusinessApiController::class, 'addLayawayPayment'])->middleware(['permission:layaways.pay', \App\Http\Middleware\IdempotentApiOperation::class]);
    Route::post('/layaways/{layaway}/liquidate', [LayawayController::class, 'liquidate'])->middleware('permission:layaways.pay');
    Route::get('/cash-cuts', [BusinessApiController::class, 'cashCuts'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::post('/cash-cuts/preview', [BusinessApiController::class, 'previewCashCut'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::post('/cash-cuts', [BusinessApiController::class, 'createCashCut'])->middleware(['role:gerente', 'permission:reports.view']);
    Route::get('/coupons', [BusinessApiController::class, 'coupons'])->middleware('permission:coupons.manage');
    Route::get('/reports', [BusinessApiController::class, 'report'])->middleware('permission:reports.view');
    Route::get('/users', [BusinessApiController::class, 'users'])->middleware(['role:gerente', 'permission:users.manage']);
    Route::get('/settings', [BusinessApiController::class, 'settings'])->middleware(['role:gerente', 'permission:settings.manage']);

});

Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureActiveApiUser::class, 'permission:products.create'])->prefix('catalog-imports')->group(function () {
    Route::get('/template',[\App\Http\Controllers\CatalogImportController::class,'template']);
    Route::post('/preview',[\App\Http\Controllers\CatalogImportController::class,'preview']);
    Route::get('/{batch}',[\App\Http\Controllers\CatalogImportController::class,'show']);
    Route::post('/{batch}/confirm',[\App\Http\Controllers\CatalogImportController::class,'confirm']);
});

require __DIR__.'/mobile.php';
