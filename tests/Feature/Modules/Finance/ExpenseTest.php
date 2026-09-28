<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Actions\CreateAccount;
use App\Modules\Finance\Actions\RecordExpense;
use App\Modules\Finance\Actions\UpdateAccount;
use App\Modules\Finance\Actions\VoidExpense;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Support\SpendingAccounts;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    $this->property = $this->contract->property()->firstOrFail();
    $this->caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $this->caretaker);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function spend(Property $property, string $paidFrom, array $overrides = []): Expense
{
    return app(RecordExpense::class)->handle($property, [
        'expense_account_id' => Account::system(AccountSubtype::MaintenanceExpense)->id,
        'paid_from_account_id' => $paidFrom,
        'amount' => 75_000,
        'spent_on' => '2026-09-15',
        'description' => 'Ganti lampu lorong',
        ...$overrides,
    ]);
}

it('lets the caretaker pay from the cash they hold, lowering what they hand over', function () {
    BillingScenario::issueDue($this->contract);
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 500_000, $this->caretaker);
    $ownCash = Account::query()->where('user_id', $this->caretaker->id)->sole();

    expect(SpendingAccounts::paidFrom($this->caretaker)->pluck('id')->all())->toBe([$ownCash->id])
        ->and(fn () => spend($this->property, Account::system(AccountSubtype::Cash)->id))
        ->toThrow(ValidationException::class, 'tidak bisa membayar');

    spend($this->property, $ownCash->id);

    expect(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(425_000);
});

it('lets the owner pay from cash or bank, and refuses a future date', function () {
    $bank = PaymentScenario::bankAccount();

    $expense = spend($this->property, $bank->ledger_account_id, ['amount' => 400_000]);

    expect($expense->amount)->toBe(400_000)
        ->and(fn () => spend($this->property, $bank->ledger_account_id, ['spent_on' => '2026-09-16']))
        ->toThrow(ValidationException::class, 'masa depan')
        ->and(fn () => spend($this->property, $bank->ledger_account_id, ['expense_account_id' => Account::system(AccountSubtype::Cash)->id]))
        ->toThrow(ValidationException::class, 'kategori');
});

it('voids an expense once, only for the owner, and never edits it', function () {
    $expense = spend($this->property, Account::system(AccountSubtype::Cash)->id);

    expect(fn () => $expense->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $expense->delete())->toThrow(LogicException::class);

    $manager = staff(Role::Manager, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $manager);
    loginAs($manager);

    expect(fn () => app(VoidExpense::class)->handle($expense, ['reason' => 'Salah catat']))->toThrow(AuthorizationException::class);

    loginAs($this->owner);
    app(VoidExpense::class)->handle($expense, ['reason' => 'Dicatat dua kali']);

    expect($expense->refresh()->isVoided())->toBeTrue()
        ->and(fn () => app(VoidExpense::class)->handle($expense, ['reason' => 'Dicatat dua kali']))
        ->toThrow(ValidationException::class, 'sudah dibatalkan');
});

it('adds expense categories and extra cash boxes to the chart of accounts', function () {
    expect(CreateAccount::suggestCode('expense'))->toBe('5-2000')
        ->and(CreateAccount::suggestCode('cash'))->toBe('1-1000-01');

    $security = app(CreateAccount::class)->handle(['kind' => 'expense', 'code' => '5-2000', 'name' => 'Beban keamanan']);
    $box = app(CreateAccount::class)->handle([
        'kind' => 'cash', 'code' => '1-1000-01', 'name' => 'Kas kecil properti', 'property_id' => $this->property->id,
    ]);

    expect($security->type)->toBe(AccountType::Expense)
        ->and($box->parent_id)->toBe(Account::system(AccountSubtype::Cash)->id)
        ->and(SpendingAccounts::expenseAccounts()->pluck('id')->all())->toContain($security->id)
        ->and(SpendingAccounts::paidFrom($this->owner)->pluck('id')->all())->toContain($box->id)
        ->and(fn () => app(CreateAccount::class)->handle(['kind' => 'expense', 'code' => '5-2000', 'name' => 'Lagi']))
        ->toThrow(ValidationException::class);
});

it('keeps system accounts active and the chart for the owner and accountant', function () {
    expect(fn () => app(UpdateAccount::class)->handle(Account::system(AccountSubtype::Receivable), ['name' => 'Piutang', 'is_active' => false]))
        ->toThrow(ValidationException::class, 'tidak bisa dinonaktifkan');

    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    app(CreateAccount::class)->handle(['kind' => 'expense', 'code' => '5-2000', 'name' => 'Beban keamanan']);
})->throws(AuthorizationException::class);
