<?php

namespace App\Modules\Finance;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Listeners\BillingJournals;
use App\Modules\Finance\Listeners\FinanceJournals;
use App\Modules\Finance\Listeners\PaymentJournals;
use App\Modules\Finance\Listeners\ProvisionChartForNewTenant;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Tenancy\Events\TenantCreated;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

class FinanceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(FinancePermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'account' => Account::class,
            'bank_account' => BankAccount::class,
            'deposit_transaction' => DepositTransaction::class,
            'fiscal_period' => FiscalPeriod::class,
            'journal_entry' => JournalEntry::class,
            'journal_line' => JournalLine::class,
            'expense' => Expense::class,
        ]);

        Event::listen(TenantCreated::class, ProvisionChartForNewTenant::class);
        Event::subscribe(BillingJournals::class);
        Event::subscribe(PaymentJournals::class);
        Event::subscribe(FinanceJournals::class);
    }
}
