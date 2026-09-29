<?php

use App\Modules\Access\Filament\App\Auth\AcceptInvitation;
use Illuminate\Support\Facades\Route;

Route::get('/undangan/{token}', AcceptInvitation::class)->name('invitation.accept');
