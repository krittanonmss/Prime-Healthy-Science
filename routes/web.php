<?php

use App\Http\Controllers\SalesController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalesController::class, 'dashboard']);
Route::get('/orders', [SalesController::class, 'orders']);
Route::get('/orders/create', [SalesController::class, 'create']);
Route::post('/orders', [SalesController::class, 'store']);
Route::get('/orders/{id}', [SalesController::class, 'show']);
Route::post('/orders/{id}/status', [SalesController::class, 'status']);
Route::post('/orders/{id}/payment', [SalesController::class, 'payment']);
Route::get('/products', [SalesController::class, 'products']);
Route::get('/products/create', [SalesController::class, 'createProduct']);
Route::post('/products', [SalesController::class, 'storeProduct']);
Route::get('/products/{id}/pricing', [SalesController::class, 'editProductPrices']);
Route::post('/products/{id}', [SalesController::class, 'price']);
Route::get('/customers', [SalesController::class, 'customers']);
Route::get('/customers/create', [SalesController::class, 'createCustomer']);
Route::get('/customers/{id}/edit', [SalesController::class, 'editCustomer']);
Route::post('/customers/{id}', [SalesController::class, 'updateCustomer']);
Route::post('/customers', [SalesController::class, 'customer']);
Route::get('/reports', [SalesController::class, 'reports']);
Route::get('/reports/export', [SalesController::class, 'exportReportForm']);
Route::get('/export', [SalesController::class, 'export']);

Route::get('/orders/{id}/document', [SalesController::class, 'document']);

Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
Route::get('/stock/update', [StockController::class, 'edit'])->name('stock.edit');
Route::post('/stock/{id}', [StockController::class, 'update']);
