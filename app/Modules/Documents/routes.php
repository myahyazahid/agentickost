<?php

use App\Modules\Documents\Http\Controllers\AttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/lampiran/{attachment}', AttachmentController::class)
    ->middleware(['auth', 'tenant', 'signed'])
    ->name('attachments.show');
