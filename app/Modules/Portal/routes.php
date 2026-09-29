<?php

use App\Modules\Portal\Http\Controllers\ContractPdfController;
use App\Modules\Portal\Http\Controllers\IconController;
use App\Modules\Portal\Http\Controllers\LogoController;
use App\Modules\Portal\Http\Controllers\LogoutController;
use App\Modules\Portal\Http\Controllers\ManifestController;
use App\Modules\Portal\Http\Controllers\ServiceWorkerController;
use App\Modules\Portal\Livewire\Account;
use App\Modules\Portal\Livewire\Announcements;
use App\Modules\Portal\Livewire\Home;
use App\Modules\Portal\Livewire\InvoiceDetail;
use App\Modules\Portal\Livewire\Invoices;
use App\Modules\Portal\Livewire\Login;
use App\Modules\Portal\Livewire\Payments;
use App\Modules\Portal\Livewire\ReportTicket;
use App\Modules\Portal\Livewire\TicketDetail;
use App\Modules\Portal\Livewire\Tickets;
use Illuminate\Support\Facades\Route;

Route::prefix('p/{tenant}')
    ->name('portal.')
    ->middleware('portal.tenant')
    ->group(function () {
        Route::get('masuk', Login::class)->name('login');
        Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');
        Route::get('sw.js', ServiceWorkerController::class)->name('service-worker');
        Route::get('ikon-{size}.png', IconController::class)->whereNumber('size')->name('icon');
        Route::get('logo', LogoController::class)->name('logo');
        Route::view('offline', 'portal::offline')->name('offline');

        Route::middleware('portal.auth')->group(function () {
            Route::get('/', Home::class)->name('home');
            Route::get('tagihan', Invoices::class)->name('invoices');
            Route::get('tagihan/{invoice}', InvoiceDetail::class)->name('invoices.show');
            Route::get('pembayaran', Payments::class)->name('payments');
            Route::get('akun', Account::class)->name('account');
            Route::get('kontrak/{contract}/pdf', ContractPdfController::class)->name('contracts.pdf');
            Route::get('laporan', Tickets::class)->name('tickets');
            Route::get('laporan/baru', ReportTicket::class)->name('tickets.create');
            Route::get('laporan/{ticket}', TicketDetail::class)->name('tickets.show');
            Route::get('pengumuman', Announcements::class)->name('announcements');
            Route::post('keluar', LogoutController::class)->name('logout');
        });
    });
