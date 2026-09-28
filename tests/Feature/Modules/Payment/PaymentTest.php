<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\IssueCreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Billing\States\Invoice\Partial;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Actions\ApplyCredit;
use App\Modules\Payment\Actions\RejectPayment;
use App\Modules\Payment\Actions\ReversePayment;
use App\Modules\Payment\Actions\VerifyPayment;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Events\PaymentReversed;
use App\Modules\Payment\Events\PaymentVerified;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\States\Payment\Rejected;
use App\Modules\Payment\States\Payment\Reversed;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

/*
 * The first invoice of the default contract (starting 20 September) holds a
 * deposit of 1.200.000 and the first month's rent of 1.200.000.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    [$this->first] = BillingScenario::issueDue($this->contract);
    PaymentScenario::bankAccount();
});

function assignedStaff(Role $role, Contract $contract): User
{
    $user = staff($role, $contract->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($contract->property()->firstOrFail(), $user);

    return $user;
}

it('verifies a transfer recorded by the owner at once and numbers its receipt', function () {
    Event::fake([PaymentVerified::class]);

    $payment = PaymentScenario::transfer($this->contract, 2_400_000, ['reference' => 'TRF 0915']);

    expect($payment->status)->toBeInstanceOf(Verified::class)
        ->and($payment->receipt_number)->toBe('KWT/2026/09/0001')
        ->and($payment->payer_id)->toBe($this->contract->payer_id)
        ->and($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(PaymentScenario::allocations($this->first))->toBe([['deposit', 1_200_000], ['rent', 1_200_000]])
        ->and(DepositLedger::balance($this->contract->id))->toBe(1_200_000);
    Event::assertDispatched(PaymentVerified::class);
});

it('takes a partial payment, deposit first, then the rest', function () {
    PaymentScenario::transfer($this->contract, 1_500_000);

    expect($this->first->refresh()->status)->toBeInstanceOf(Partial::class)
        ->and($this->first->balance_amount)->toBe(900_000)
        ->and(PaymentScenario::allocations($this->first))->toBe([['deposit', 1_200_000], ['rent', 300_000]]);

    PaymentScenario::transfer($this->contract, 900_000);

    expect($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($this->first->balance_amount)->toBe(0)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0);
});

it('keeps an overpayment as credit and uses it on the next invoice', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_500_000);

    expect(CreditLedger::balance($this->contract->id))->toBe(100_000)
        ->and(CreditTransaction::query()->sole()->only(['type', 'payment_id']))
        ->toBe(['type' => CreditTransactionType::Overpayment, 'payment_id' => $payment->id]);

    $this->travelTo('2026-10-15 03:00:00');
    [$second] = BillingScenario::issueDue($this->contract);

    expect($second->refresh()->status)->toBeInstanceOf(Partial::class)
        ->and($second->paid_amount)->toBe(100_000)
        ->and($second->balance_amount)->toBe(1_100_000)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0)
        ->and(PaymentScenario::allocations($second))->toBe([['rent', 100_000]]);
});

it('pays several invoices with one payment, oldest first', function () {
    $this->travelTo('2026-10-15 03:00:00');
    [$second] = BillingScenario::issueDue($this->contract);

    PaymentScenario::transfer($this->contract, 3_000_000);

    expect($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($second->refresh()->status)->toBeInstanceOf(Partial::class)
        ->and($second->balance_amount)->toBe(600_000);
});

it('pays the invoices chosen by hand and keeps the rest as credit', function () {
    $this->travelTo('2026-10-15 03:00:00');
    [$second] = BillingScenario::issueDue($this->contract);

    PaymentScenario::transfer($this->contract, 1_500_000, [
        'allocations' => [['invoice_id' => $second->id, 'amount' => 1_200_000]],
    ]);

    expect($second->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($this->first->refresh()->paid_amount)->toBe(0)
        ->and(CreditLedger::balance($this->contract->id))->toBe(300_000);
});

it('refuses a manual allocation above what the invoice owes', function () {
    PaymentScenario::transfer($this->contract, 3_000_000, [
        'allocations' => [['invoice_id' => $this->first->id, 'amount' => 2_500_000]],
    ]);
})->throws(ValidationException::class, 'hanya Rp2.400.000');

it('puts a transfer from the caretaker in the verification queue with its proof', function () {
    Storage::fake();
    $caretaker = assignedStaff(Role::Caretaker, $this->contract);
    $manager = assignedStaff(Role::Manager, $this->contract);
    loginAs($caretaker);

    $payment = PaymentScenario::transfer($this->contract, 2_400_000, ['proofs' => [PaymentScenario::proof()]]);

    expect($payment->status)->toBeInstanceOf(Pending::class)
        ->and($payment->receipt_number)->toBeNull()
        ->and($payment->attachmentPaths(AttachmentCollection::PaymentProof))->toHaveCount(1)
        ->and($this->first->refresh()->paid_amount)->toBe(0)
        ->and(fn () => app(VerifyPayment::class)->handle($payment))->toThrow(AuthorizationException::class);

    loginAs($manager);
    app(VerifyPayment::class)->handle($payment);

    expect($payment->refresh()->status)->toBeInstanceOf(Verified::class)
        ->and($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(fn () => app(VerifyPayment::class)->handle($payment))->toThrow(ValidationException::class, 'sudah diperiksa');
});

it('rejects a pending transfer with a reason and leaves the invoice unpaid', function () {
    loginAs(assignedStaff(Role::Caretaker, $this->contract));
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);
    loginAs($this->owner);

    expect(fn () => app(RejectPayment::class)->handle($payment, ['reason' => 'x']))->toThrow(ValidationException::class);

    app(RejectPayment::class)->handle($payment, ['reason' => 'Dana tidak masuk ke rekening']);

    expect($payment->refresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($payment->rejection_reason)->toBe('Dana tidak masuk ke rekening')
        ->and($this->first->refresh()->status)->toBeInstanceOf(Issued::class);
});

it('verifies cash the caretaker received and refuses cash received by someone else', function () {
    $caretaker = assignedStaff(Role::Caretaker, $this->contract);
    loginAs($caretaker);

    $payment = PaymentScenario::cash($this->contract, 1_000_000, $caretaker);

    expect($payment->status)->toBeInstanceOf(Verified::class)
        ->and($payment->bank_account_id)->toBeNull()
        ->and(fn () => PaymentScenario::cash($this->contract, 1_000, $this->owner))
        ->toThrow(ValidationException::class, 'Anda terima sendiri');
});

it('requires a destination account for a transfer and a receiver for cash', function () {
    expect(fn () => PaymentScenario::transfer($this->contract, 1_000, ['bank_account_id' => null]))
        ->toThrow(ValidationException::class)
        ->and(fn () => PaymentScenario::cash($this->contract, 1_000, $this->owner, ['received_by_user_id' => null]))
        ->toThrow(ValidationException::class)
        ->and(fn () => PaymentScenario::transfer($this->contract, 1_000, ['paid_at' => '2026-09-16 10:00:00']))
        ->toThrow(ValidationException::class, 'masa depan');
});

it('reverses a payment: the invoice owes again and the deposit received is taken back', function () {
    Event::fake([PaymentReversed::class]);
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);

    app(ReversePayment::class)->handle($payment, ['reason' => 'Transfer ditolak bank']);

    expect($payment->refresh()->status)->toBeInstanceOf(Reversed::class)
        ->and($payment->reversal_reason)->toBe('Transfer ditolak bank')
        ->and($this->first->refresh()->status)->toBeInstanceOf(Issued::class)
        ->and($this->first->balance_amount)->toBe(2_400_000)
        ->and(PaymentScenario::allocations($this->first))->toBe([])
        ->and(DepositLedger::balance($this->contract->id))->toBe(0)
        ->and(fn () => app(ReversePayment::class)->handle($payment, ['reason' => 'Coba lagi dua kali']))
        ->toThrow(ValidationException::class, 'terverifikasi');
    Event::assertDispatched(PaymentReversed::class);
});

it('takes back credit already used on a later invoice when the overpayment is reversed', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);
    $extra = PaymentScenario::transfer($this->contract, 500_000);
    $this->travelTo('2026-10-15 03:00:00');
    [$second] = BillingScenario::issueDue($this->contract);

    expect($second->paid_amount)->toBe(500_000);

    app(ReversePayment::class)->handle($extra, ['reason' => 'Tercatat dua kali']);

    expect($second->refresh()->paid_amount)->toBe(0)
        ->and($second->status)->toBeInstanceOf(Issued::class)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0)
        ->and($this->first->refresh()->status)->toBeInstanceOf(Paid::class);
});

it('keeps the credit that is left after taking back part of a used credit', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);
    $a = PaymentScenario::transfer($this->contract, 300_000);
    PaymentScenario::transfer($this->contract, 200_000);
    $this->travelTo('2026-10-15 03:00:00');
    [$second] = BillingScenario::issueDue($this->contract);

    app(ReversePayment::class)->handle($a, ['reason' => 'Salah kontrak']);

    expect($second->refresh()->paid_amount)->toBe(200_000)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0);
});

it('refuses to reverse a payment whose deposit was already refunded', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);
    app(RefundDeposit::class)->handle($this->contract, [
        'amount' => 1_200_000,
        'account_id' => PaymentScenario::bankAccount()->ledger_account_id,
    ]);

    app(ReversePayment::class)->handle($payment, ['reason' => 'Transfer ditolak bank']);
})->throws(ValidationException::class, 'belum bisa dibalik');

it('lets only the owner reverse payments', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);
    loginAs(assignedStaff(Role::Manager, $this->contract));

    app(ReversePayment::class)->handle($payment, ['reason' => 'Transfer ditolak bank']);
})->throws(AuthorizationException::class);

it('uses credit on open invoices when asked', function () {
    PaymentScenario::transfer($this->contract, 2_400_000, [
        'allocations' => [['invoice_id' => $this->first->id, 'amount' => 2_000_000]],
    ]);

    expect(app(ApplyCredit::class)->handle($this->contract))->toBe(400_000)
        ->and($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(fn () => app(ApplyCredit::class)->handle($this->contract))->toThrow(ValidationException::class);
});

it('moves money paid for a credited part of a paid invoice to the credit balance', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);

    app(IssueCreditNote::class)->handle($this->first, [
        'allocation_category' => 'rent', 'amount' => 200_000, 'reason' => 'Air mati seminggu',
    ]);

    expect($this->first->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($this->first->paid_amount)->toBe(2_200_000)
        ->and($this->first->balance_amount)->toBe(0)
        ->and(PaymentScenario::allocations($this->first))->toBe([['deposit', 1_200_000], ['rent', 1_000_000]])
        ->and(CreditLedger::balance($this->contract->id))->toBe(200_000);

    app(ReversePayment::class)->handle($payment, ['reason' => 'Transfer ditolak bank']);

    expect($this->first->refresh()->balance_amount)->toBe(2_200_000)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0);
});

it('never edits or deletes a checked payment', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);

    expect(fn () => $payment->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $payment->delete())->toThrow(LogicException::class)
        ->and(fn () => $payment->allocations()->firstOrFail()->update(['amount' => 1]))->toThrow(LogicException::class);
});

it('keeps each payment equal to what it allocated plus what it left as credit', function () {
    $payments = [
        PaymentScenario::transfer($this->contract, 1_000_000),
        PaymentScenario::transfer($this->contract, 1_700_000),
    ];
    $this->travelTo('2026-10-15 03:00:00');
    BillingScenario::issueDue($this->contract);
    app(IssueCreditNote::class)->handle($this->first, [
        'allocation_category' => 'rent', 'amount' => 100_000, 'reason' => 'Potongan telat pindah',
    ]);

    foreach ($payments as $payment) {
        $allocated = (int) $payment->allocations()->active()->sum('amount');
        $credited = (int) CreditTransaction::query()->where('payment_id', $payment->id)->sum('amount');

        expect($allocated + $credited)->toBe($payment->amount);
    }

    expect(Invoice::query()->sum('paid_amount'))->toEqual(
        (int) PaymentAllocation::query()->active()->sum('amount'),
    );
});
