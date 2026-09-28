<?php

use App\Modules\Onboarding\Http\Controllers\ImportTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/dokumen/template-impor', ImportTemplateController::class)
    ->middleware(['auth', 'tenant'])
    ->name('onboarding.import-template');
