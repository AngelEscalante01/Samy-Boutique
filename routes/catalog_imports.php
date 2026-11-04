<?php

use App\Http\Controllers\CatalogImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:products.create'])->prefix('catalog-imports')->name('catalog-imports.')->group(function () {
    Route::get('/export-update', [\App\Http\Controllers\CatalogImportController::class, 'exportUpdate'])->name('export-update');
    Route::get('/', [CatalogImportController::class, 'index'])->name('index');
    Route::get('/template', [CatalogImportController::class, 'template'])->name('template');
    Route::post('/preview', [CatalogImportController::class, 'preview'])->name('preview');
    Route::get('/{batch}', [CatalogImportController::class, 'show'])->name('show');
    Route::post('/{batch}/confirm', [CatalogImportController::class, 'confirm'])->name('confirm');
});
