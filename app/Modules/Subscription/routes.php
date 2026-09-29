<?php

use App\Modules\Subscription\Http\Controllers\DataExportController;
use Illuminate\Support\Facades\Route;

Route::get('/ekspor-data/{file}', DataExportController::class)
    ->middleware(['auth', 'tenant', 'signed'])
    ->name('subscription.export.download');
