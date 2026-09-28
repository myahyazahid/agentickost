<?php

namespace App\Modules\Payment;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Listeners\ApplyCreditToIssuedInvoice;
use App\Modules\Payment\Listeners\ReleaseOverpaidCredit;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

class PaymentServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(PaymentPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'payment' => Payment::class,
            'payment_allocation' => PaymentAllocation::class,
            'credit_transaction' => CreditTransaction::class,
            'staff_cash_handover' => StaffCashHandover::class,
        ]);

        Event::listen(InvoiceIssued::class, ApplyCreditToIssuedInvoice::class);
        Event::listen(CreditNoteIssued::class, ReleaseOverpaidCredit::class);
    }
}
