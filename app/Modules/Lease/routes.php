<?php

use App\Modules\Lease\Http\Controllers\ContractPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/dokumen/kontrak/{contract}', ContractPdfController::class)
    ->middleware(['auth', 'tenant'])
    ->name('lease.contracts.pdf');
