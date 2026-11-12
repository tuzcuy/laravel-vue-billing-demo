<?php

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoicePaymentController;
use App\Http\Controllers\Api\InvoiceStatusController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard/stats', [DashboardController::class, 'stats']);

Route::apiResource('customers', CustomerController::class);
Route::apiResource('invoices', InvoiceController::class);

Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store']);
Route::post('invoices/{invoice}/send', [InvoiceStatusController::class, 'send']);
Route::post('invoices/{invoice}/void', [InvoiceStatusController::class, 'void']);
