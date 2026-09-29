<?php

use App\Modules\Access\Http\Controllers\EndImpersonationController;
use Illuminate\Support\Facades\Route;

Route::post('/impersonasi/selesai', EndImpersonationController::class)
    ->middleware(['auth', 'tenant'])
    ->name('access.impersonation.end');
