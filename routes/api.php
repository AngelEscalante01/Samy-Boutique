<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\Pos\CashCutApiController;
use App\Http\Controllers\Api\Pos\CatalogController as PosCatalogController;
use App\Http\Controllers\Api\Pos\CouponController as PosCouponController;
use App\Http\Controllers\Api\Pos\CustomerController as PosCustomerController;
use App\Http\Controllers\Api\Pos\DashboardApiController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\Pos\LayawayController as PosLayawayController;
use App\Http\Controllers\Api\V1\LayawayController;
use App\Http\Controllers\Api\Pos\ProductController as PosProductController;
use App\Http\Controllers\Api\Pos\ReportApiController;
use App\Http\Controllers\Api\Pos\SaleController as PosSaleController;
use App\Http\Controllers\Api\Pos\UserController as PosUserController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:api-login')
    ->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/products', [PosProductController::class, 'index'])->name('api.products.index');
    Route::get('/products/{id}', [PosProductController::class, 'show'])->name('api.products.show');

    Route::get('/customers', [PosCustomerController::class, 'index'])->name('api.customers.index');
    Route::get('/customers/{customer}', [PosCustomerController::class, 'show'])->name('api.customers.show');
    Route::post('/customers', [PosCustomerController::class, 'store'])->name('api.customers.store');
    Route::put('/customers/{customer}', [PosCustomerController::class, 'update'])->name('api.customers.update');

    Route::get('/sales', [PosSaleController::class, 'index'])->name('api.sales.index');
    Route::post('/sales', [PosSaleController::class, 'store'])->name('api.sales.store');
    Route::get('/sales/{sale}', [PosSaleController::class, 'show'])->name('api.sales.show');
    Route::get('/sales/{sale}/ticket', [PosSaleController::class, 'ticket'])->name('api.sales.ticket');

    Route::get('/layaways', [PosLayawayController::class, 'index'])->name('api.layaways.index');
    Route::get('/layaways/{layaway}', [PosLayawayController::class, 'show'])->name('api.layaways.show');
    Route::post('/layaways', [PosLayawayController::class, 'store'])->name('api.layaways.store');
    Route::post('/layaways/{layaway}/payments', [PosLayawayController::class, 'addPayment'])->name('api.layaways.payments.store');

    // Catálogos (categorías, tallas, colores)
    Route::get('/catalogs', [PosCatalogController::class, 'index'])->name('api.catalogs.index');
    Route::get('/catalogs/categories', [PosCatalogController::class, 'categories'])->name('api.catalogs.categories');
    Route::get('/catalogs/sizes', [PosCatalogController::class, 'sizes'])->name('api.catalogs.sizes');
    Route::get('/catalogs/colors', [PosCatalogController::class, 'colors'])->name('api.catalogs.colors');

    // Cupones
    Route::get('/coupons', [PosCouponController::class, 'index'])->name('api.coupons.index');

    // Usuarios
    Route::get('/users', [PosUserController::class, 'index'])->name('api.users.index');

    // Dashboard
    Route::get('/dashboard/summary', [DashboardApiController::class, 'summary'])->name('api.dashboard.summary');

    // Reportes
    Route::get('/reports', [ReportApiController::class, 'index'])->name('api.reports.index');

    // Corte diario
    Route::get('/cash-cuts', [CashCutApiController::class, 'index'])->name('api.cash-cuts.index');
    Route::post('/cash-cuts/preview', [CashCutApiController::class, 'preview'])->name('api.cash-cuts.preview');
    Route::post('/cash-cuts', [CashCutApiController::class, 'store'])->name('api.cash-cuts.store');
});

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('api.v1.auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('api.v1.auth.logout-all');

        Route::get('/products', [ProductController::class, 'index'])->name('api.v1.products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('api.v1.products.show');

        Route::get('/customers', [CustomerController::class, 'index'])->name('api.v1.customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('api.v1.customers.show');

        Route::get('/sales', [SaleController::class, 'index'])->name('api.v1.sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('api.v1.sales.show');
        Route::post('/sales', [SaleController::class, 'store'])->name('api.v1.sales.store');

        Route::get('/layaways', [LayawayController::class, 'index'])->name('api.v1.layaways.index');
        Route::get('/layaways/{layaway}', [LayawayController::class, 'show'])->name('api.v1.layaways.show');
        Route::post('/layaways', [LayawayController::class, 'store'])->name('api.v1.layaways.store');
        Route::post('/layaways/{layaway}/payments', [LayawayController::class, 'addPayment'])->name('api.v1.layaways.payments.store');
        Route::post('/layaways/{layaway}/liquidate', [LayawayController::class, 'liquidate'])->name('api.v1.layaways.liquidate');
        Route::post('/layaways/{layaway}/cancel', [LayawayController::class, 'cancel'])->name('api.v1.layaways.cancel');
    });
});
