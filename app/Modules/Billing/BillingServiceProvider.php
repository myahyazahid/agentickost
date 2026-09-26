<?php

namespace App\Modules\Billing;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Billing\Console\AccruePenalties;
use App\Modules\Billing\Console\IssueRentInvoices;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\PenaltyAccrual;
use App\Modules\Billing\Models\UtilityRate;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(BillingPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'invoice' => Invoice::class,
            'invoice_item' => InvoiceItem::class,
            'penalty_accrual' => PenaltyAccrual::class,
            'credit_note' => CreditNote::class,
            'utility_rate' => UtilityRate::class,
            'meter_reading' => MeterReading::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([IssueRentInvoices::class, AccruePenalties::class]);
        }
    }
}
