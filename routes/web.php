<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MappingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// ---- Produk ----
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/export', [ProductController::class, 'export'])->name('export');
    Route::post('/import', [ProductController::class, 'import'])->name('import');
    Route::post('/refine-all', [ProductController::class, 'refineAll'])->name('refineAll');
    Route::post('/regenerate-all-descriptions', [ProductController::class, 'regenerateAllDescriptions'])->name('regenerateAll');
    Route::get('/{product}', [ProductController::class, 'show'])->name('show');
    Route::post('/{product}/refine', [ProductController::class, 'refine'])->name('refine');
    Route::post('/{product}/regenerate-description', [ProductController::class, 'regenerateDescription'])->name('regenerateDescription');
});

// ---- Kendaraan ----
Route::prefix('vehicles')->name('vehicles.')->group(function () {
    Route::get('/', [VehicleController::class, 'index'])->name('index');
    Route::get('/export', [VehicleController::class, 'export'])->name('export');
    Route::post('/import', [VehicleController::class, 'import'])->name('import');
    Route::post('/refine-all', [VehicleController::class, 'refineAll'])->name('refineAll');
    Route::post('/{vehicle}/refine', [VehicleController::class, 'refine'])->name('refine');
});

// ---- Katalog PDF + Auto-mapping ----
Route::prefix('catalog')->name('catalog.')->group(function () {
    Route::get('/', [CatalogController::class, 'index'])->name('index');
    Route::post('/import', [CatalogController::class, 'import'])->name('import');
    Route::post('/{batch}/generate-mappings', [CatalogController::class, 'generateMappings'])->name('generateMappings');
});

// ---- Product Mapping ----
Route::get('/mappings', [MappingController::class, 'index'])->name('mappings.index');
Route::get('/mappings/export', [MappingController::class, 'export'])->name('mappings.export');
