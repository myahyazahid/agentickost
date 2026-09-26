<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Actions\CreateBankAccount;
use App\Modules\Finance\Actions\UpdateBankAccount;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Support\ChartOfAccounts;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bankInput(array $overrides = []): array
{
    return [
        'kind' => 'bank',
        'provider_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Siti Aminah',
        'is_default' => false,
        ...$overrides,
    ];
}

it('gives every new tenant the built-in chart of accounts', function () {
    $tenant = Tenant::factory()->create();

    $codes = tenancy()->run($tenant, fn () => Account::query()->orderBy('code')->pluck('code')->all());

    expect($codes)->toBe(array_column(ChartOfAccounts::defaults(), 'code'))
        ->and(tenancy()->run($tenant, fn () => Account::system(AccountSubtype::Receivable)->name))->toBe('Piutang penghuni');
});

it('opens a ledger account under the bank group for each bank account', function () {
    loginAs(staff(Role::Owner));

    $first = app(CreateBankAccount::class)->handle(bankInput());
    $second = app(CreateBankAccount::class)->handle(bankInput(['provider_name' => 'Mandiri', 'account_number' => '9876543210']));

    $ledger = $second->ledgerAccount()->firstOrFail();
    expect($first->ledgerAccount()->firstOrFail()->code)->toBe('1-1200-01')
        ->and($ledger->code)->toBe('1-1200-02')
        ->and($ledger->name)->toBe('Mandiri 9876543210')
        ->and($ledger->parent_id)->toBe(Account::system(AccountSubtype::Bank)->id);
});

it('keeps one default account per property', function () {
    loginAs(staff(Role::Owner));
    $property = Property::factory()->create();

    $shared = app(CreateBankAccount::class)->handle(bankInput(['is_default' => true]));
    $first = app(CreateBankAccount::class)->handle(bankInput(['property_id' => $property->id, 'is_default' => true]));
    $second = app(CreateBankAccount::class)->handle(bankInput(['property_id' => $property->id, 'is_default' => true]));

    expect($shared->fresh()?->is_default)->toBeTrue()
        ->and($first->fresh()?->is_default)->toBeFalse()
        ->and($second->fresh()?->is_default)->toBeTrue();
});

it('deactivates the ledger account together with the bank account', function () {
    loginAs(staff(Role::Owner));
    $bankAccount = app(CreateBankAccount::class)->handle(bankInput());

    app(UpdateBankAccount::class)->handle($bankAccount, bankInput(['is_active' => false]));

    expect($bankAccount->ledgerAccount()->firstOrFail()->is_active)->toBeFalse();
});

it('leaves bank accounts to the owner', function (Role $role) {
    loginAs(staff($role));

    app(CreateBankAccount::class)->handle(bankInput());
})->with([Role::Manager, Role::Accountant])->throws(AuthorizationException::class);

it('does not show a bank account of one property in another tenant', function () {
    $owner = loginAs(staff(Role::Owner));
    app(CreateBankAccount::class)->handle(bankInput());

    expect(tenancy()->run(Tenant::factory()->create(), fn () => BankAccount::query()->count()))->toBe(0)
        ->and(BankAccount::query()->where('tenant_id', $owner->tenant_id)->count())->toBe(1);
});
