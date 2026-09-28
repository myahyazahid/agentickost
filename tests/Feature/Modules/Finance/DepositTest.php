<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\CreateAdhocInvoice;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Actions\DeductDeposit;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Actions\TransferDeposit;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositDeducted;
use App\Modules\Finance\Events\DepositRefunded;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Finance\Support\DepositsHeld;
use App\Modules\Lease\Actions\RenewContract;
use App\Modules\Payment\Actions\ApplyDepositToInvoice;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

/*
 * The default contract bills a 1.200.000 deposit on its first invoice; paying
 * that invoice in full puts the deposit in the ledger.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    [$this->first] = BillingScenario::issueDue($this->contract);
    PaymentScenario::transfer($this->contract, 2_400_000);
});

it('records the deposit as received when its invoice component is paid', function () {
    $entry = DepositTransaction::query()->sole();

    expect($entry->type)->toBe(DepositTransactionType::Received)
        ->and($entry->amount)->toBe(1_200_000)
        ->and($entry->paymentAllocation?->payment_id)->not->toBeNull()
        ->and(DepositLedger::balance($this->contract->id))->toBe(1_200_000);
});

it('deducts deposit with a reason and photos', function () {
    Storage::fake();
    Event::fake([DepositDeducted::class]);
    $photo = app(TenantStorage::class)->path(AttachmentCollection::Photo->directory().'/dinding.jpg');
    Storage::put($photo, 'jpeg-bytes');

    $entry = app(DeductDeposit::class)->handle($this->contract, [
        'amount' => 250_000, 'reason' => 'Cat dinding kamar rusak', 'photos' => [$photo],
    ]);

    expect($entry->amount)->toBe(-250_000)
        ->and($entry->type)->toBe(DepositTransactionType::Deducted)
        ->and($entry->attachmentPaths(AttachmentCollection::Photo))->toBe([$photo])
        ->and(DepositLedger::balance($this->contract->id))->toBe(950_000);
    Event::assertDispatched(DepositDeducted::class);
});

it('requires a reason to deduct deposit', function () {
    app(DeductDeposit::class)->handle($this->contract, ['amount' => 100_000, 'reason' => '']);
})->throws(ValidationException::class);

it('refunds no more than the deposit held', function () {
    Event::fake([DepositRefunded::class]);
    $account = PaymentScenario::bankAccount()->ledger_account_id;

    expect(fn () => app(RefundDeposit::class)->handle($this->contract, ['amount' => 1_200_001, 'account_id' => $account]))
        ->toThrow(ValidationException::class, 'hanya Rp1.200.000');

    $entry = app(RefundDeposit::class)->handle($this->contract, ['amount' => 1_200_000, 'account_id' => $account]);

    expect($entry->account_id)->toBe($account)
        ->and(DepositLedger::balance($this->contract->id))->toBe(0);
    Event::assertDispatched(DepositRefunded::class);
});

it('moves deposit to the renewal contract', function () {
    $renewal = app(RenewContract::class)->handle($this->contract, [
        'start_date' => '2027-09-20', 'rent_amount' => 1_300_000, 'deposit_amount' => 1_200_000,
    ]);

    app(TransferDeposit::class)->handle($this->contract, ['to_contract_id' => $renewal->id, 'amount' => 1_200_000]);

    expect(DepositLedger::balance($this->contract->id))->toBe(0)
        ->and(DepositLedger::balance($renewal->id))->toBe(1_200_000)
        ->and(DepositTransaction::query()->where('contract_id', $renewal->id)->sole()->related_contract_id)->toBe($this->contract->id);
});

it('pays an invoice from the deposit, never its own deposit line', function () {
    $damage = app(CreateAdhocInvoice::class)->handle($this->contract->property()->firstOrFail(), [
        'contract_id' => $this->contract->id,
        'due_date' => '2026-09-30',
        'issue_now' => true,
        'items' => [['type' => 'damage', 'description' => 'Ganti kunci', 'amount' => 150_000]],
    ]);

    expect(fn () => app(ApplyDepositToInvoice::class)->handle($damage, ['amount' => 200_000, 'reason' => 'Disetujui owner']))
        ->toThrow(ValidationException::class, 'paling banyak Rp150.000');

    $entry = app(ApplyDepositToInvoice::class)->handle($damage, ['amount' => 150_000, 'reason' => 'Disetujui owner']);

    expect($entry->invoice_id)->toBe($damage->id)
        ->and($damage->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(PaymentScenario::allocations($damage))->toBe([['other', 150_000]])
        ->and(DepositLedger::balance($this->contract->id))->toBe(1_050_000);
});

it('keeps deposit changes for the owner', function () {
    $manager = staff(Role::Manager, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contract->property()->firstOrFail(), $manager);
    loginAs($manager);

    app(DeductDeposit::class)->handle($this->contract, ['amount' => 1_000, 'reason' => 'Coba potong deposit']);
})->throws(AuthorizationException::class);

it('never edits or deletes a deposit entry', function () {
    $entry = DepositTransaction::query()->sole();

    expect(fn () => $entry->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

it('reports the deposit held per property', function () {
    $other = LeaseScenario::active(overrides: ['deposit_amount' => 500_000]);
    [$otherInvoice] = BillingScenario::issueDue($other);
    PaymentScenario::transfer($other, $otherInvoice->refresh()->balance_amount);
    app(DeductDeposit::class)->handle($this->contract, ['amount' => 200_000, 'reason' => 'Kunci hilang']);

    $rows = DepositsHeld::perProperty($this->owner)->get()->keyBy('id');

    expect((int) $rows[$this->contract->property_id]->deposit_held_amount)->toBe(1_000_000)
        ->and((int) $rows[$this->contract->property_id]->contracts_holding_count)->toBe(1)
        ->and((int) $rows[$other->property_id]->deposit_held_amount)->toBe(500_000);
});
