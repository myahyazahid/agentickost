<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Payment\Actions\ConfirmCashHandover;
use App\Modules\Payment\Actions\DisputeCashHandover;
use App\Modules\Payment\Actions\RecordCashHandover;
use App\Modules\Payment\Actions\ReversePayment;
use App\Modules\Payment\Events\CashHandoverConfirmed;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Handover\Confirmed;
use App\Modules\Payment\States\Handover\Disputed;
use App\Modules\Payment\States\Handover\Pending;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

/*
 * Roadmap M1.4 done criterion: cash paid through the caretaker and a
 * handover with a difference leave the right balances.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    [$this->first] = BillingScenario::issueDue($this->contract);
    $this->property = $this->contract->property()->firstOrFail();
    $this->caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $this->caretaker);
    $this->kas = Account::system(AccountSubtype::Cash);
});

function handOver(int $amount, array $overrides = []): StaffCashHandover
{
    return app(RecordCashHandover::class)->handle(test()->property, [
        'actual_amount' => $amount,
        'destination_account_id' => test()->kas->id,
        'handed_over_at' => now()->toDateTimeString(),
        ...$overrides,
    ]);
}

it('counts cash paid through the caretaker as cash in their hands', function () {
    loginAs($this->caretaker);

    PaymentScenario::cash($this->contract, 1_400_000, $this->caretaker);
    PaymentScenario::cash($this->contract, 1_000_000, $this->caretaker);

    expect($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(2_400_000)
        ->and(Account::query()->where('user_id', $this->caretaker->id)->sole()->name)->toBe("Kas di tangan {$this->caretaker->name}");
});

it('sends cash the owner receives straight to the cash account', function () {
    PaymentScenario::cash($this->contract, 2_400_000, $this->owner);

    expect(StaffCash::balance($this->owner->id, $this->property->id))->toBe(2_400_000)
        ->and(StaffCash::tracks($this->owner))->toBeFalse()
        ->and(Account::query()->where('user_id', $this->owner->id)->exists())->toBeFalse()
        ->and(fn () => handOver(2_400_000))->toThrow(ValidationException::class, 'langsung masuk kas');
});

it('confirms a handover that matches the cash held', function () {
    Event::fake([CashHandoverConfirmed::class]);
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 2_400_000, $this->caretaker);

    $handover = handOver(2_400_000);

    expect($handover->status)->toBeInstanceOf(Pending::class)
        ->and($handover->expected_amount)->toBe(2_400_000)
        ->and(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(0);

    loginAs($this->owner);
    app(ConfirmCashHandover::class)->handle($handover);

    expect($handover->refresh()->status)->toBeInstanceOf(Confirmed::class)
        ->and($handover->difference_amount)->toBe(0)
        ->and($handover->confirmed_by)->toBe($this->owner->id);
    Event::assertDispatched(CashHandoverConfirmed::class);
});

it('flags a short handover and needs an explanation to confirm it', function () {
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 2_400_000, $this->caretaker);
    $handover = handOver(2_400_000);
    loginAs($this->owner);

    expect(fn () => app(ConfirmCashHandover::class)->handle($handover, ['actual_amount' => 2_350_000]))
        ->toThrow(ValidationException::class, 'Kurang Rp50.000');

    app(ConfirmCashHandover::class)->handle($handover, [
        'actual_amount' => 2_350_000,
        'difference_note' => 'Dipakai beli lampu lorong, nota menyusul',
    ]);

    expect($handover->refresh()->status)->toBeInstanceOf(Confirmed::class)
        ->and($handover->difference_amount)->toBe(-50_000)
        ->and($handover->difference_note)->toBe('Dipakai beli lampu lorong, nota menyusul')
        ->and(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(0)
        ->and(fn () => $handover->update(['actual_amount' => 2_400_000]))->toThrow(LogicException::class);
});

it('carries cash received after a handover into the next one', function () {
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 1_000_000, $this->caretaker);
    handOver(1_000_000);
    PaymentScenario::cash($this->contract, 1_400_000, $this->caretaker);

    expect(handOver(1_400_000)->expected_amount)->toBe(1_400_000);
});

it('keeps a disputed handover open until it is confirmed with an explanation', function () {
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 2_400_000, $this->caretaker);
    $handover = handOver(2_400_000);
    loginAs($this->owner);

    app(DisputeCashHandover::class)->handle($handover, ['dispute_note' => 'Uang di amplop hanya 2,3 juta']);

    expect($handover->refresh()->status)->toBeInstanceOf(Disputed::class);

    app(ConfirmCashHandover::class)->handle($handover, [
        'actual_amount' => 2_300_000,
        'difference_note' => 'Kekurangan dipotong dari honor bulan depan',
    ]);

    expect($handover->refresh()->status)->toBeInstanceOf(Confirmed::class)
        ->and($handover->difference_amount)->toBe(-100_000);
});

it('lowers the cash held when a cash payment is reversed', function () {
    loginAs($this->caretaker);
    $payment = PaymentScenario::cash($this->contract, 1_000_000, $this->caretaker);
    loginAs($this->owner);

    app(ReversePayment::class)->handle($payment, ['reason' => 'Dicatat di kontrak yang salah']);

    expect(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(0);
});

it('lets staff hand over only their own cash and only the owner confirm', function () {
    $other = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $other);
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 1_000_000, $this->caretaker);

    expect(fn () => handOver(1_000_000, ['staff_user_id' => $other->id]))->toThrow(AuthorizationException::class);

    $handover = handOver(1_000_000);

    expect(fn () => app(ConfirmCashHandover::class)->handle($handover))->toThrow(AuthorizationException::class);
});
