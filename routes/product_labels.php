<?php

use App\Http\Controllers\ProductLabelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'permission:products.view'])->group(function () {
    Route::get('/product-labels', [ProductLabelController::class, 'index'])->name('product-labels.index');
    Route::post('/product-labels', [ProductLabelController::class, 'store'])->name('product-labels.store');
    Route::get('/product-labels/{job}', [ProductLabelController::class, 'show'])->name('product-labels.show');
});
