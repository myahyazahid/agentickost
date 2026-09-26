<?php

use App\Modules\Billing\Http\Controllers\InvoicePdfController;
use App\Modules\Billing\Http\Controllers\SharedInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/dokumen/tagihan/{invoice}', InvoicePdfController::class)
    ->middleware(['auth', 'tenant'])
    ->name('billing.invoices.pdf');

Route::get('/tagihan/{invoice}', SharedInvoiceController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('billing.invoices.shared');
