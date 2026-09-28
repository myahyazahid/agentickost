<?php

use App\Modules\Payment\Http\Controllers\ReceiptPdfController;
use App\Modules\Payment\Http\Controllers\SharedReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/dokumen/kuitansi/{payment}', ReceiptPdfController::class)
    ->middleware(['auth', 'tenant'])
    ->name('payment.receipts.pdf');

Route::get('/kuitansi/{payment}', SharedReceiptController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('payment.receipts.shared');
