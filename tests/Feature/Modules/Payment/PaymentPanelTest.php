<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Filament\App\Pages\DepositsHeld;
use App\Modules\Finance\Filament\App\RelationManagers\DepositTransactionsRelationManager;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Filament\App\Resources\Contracts\Pages\ViewContract;
use App\Modules\Payment\Filament\App\Pages\StaffCashBalances;
use App\Modules\Payment\Filament\App\RelationManagers\CreditTransactionsRelationManager;
use App\Modules\Payment\Filament\App\Resources\CashHandovers\Pages\ListCashHandovers;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\ListPayments;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\RecordPayment;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\ViewPayment;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Handover\Confirmed;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\States\Payment\Rejected;
use App\Modules\Payment\States\Payment\Reversed;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Payment\Support\ReceiptDocument;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active();
    [$this->invoice] = BillingScenario::issueDue($this->contract);
    $this->bank = PaymentScenario::bankAccount();
    $this->caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contract->property()->firstOrFail(), $this->caretaker);
});

it('records a transfer from the form opened on an invoice', function () {
    Livewire::test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
        ->assertActionVisible('recordPayment');

    Livewire::withQueryParams(['kontrak' => $this->contract->id])
        ->test(RecordPayment::class)
        ->assertSchemaStateSet(['contract_id' => $this->contract->id, 'property_id' => $this->contract->property_id])
        ->assertSee('1 tagihan belum lunas, total sisa Rp2.400.000.')
        ->fillForm([
            'method' => 'transfer',
            'amount' => '2400000',
            'bank_account_id' => $this->bank->id,
            'reference' => 'TRF 0915',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Pembayaran dicatat dan tagihan diperbarui');

    expect(Payment::query()->sole()->status)->toBeInstanceOf(Verified::class)
        ->and($this->invoice->refresh()->status)->toBeInstanceOf(Paid::class);
});

it('allocates by hand from the form', function () {
    Livewire::test(RecordPayment::class)
        ->fillForm([
            'property_id' => $this->contract->property_id,
            'contract_id' => $this->contract->id,
            'method' => 'transfer',
            'amount' => '2500000',
            'bank_account_id' => $this->bank->id,
            'allocation_mode' => 'manual',
            'allocations' => [['invoice_id' => $this->invoice->id, 'amount' => '1000000']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($this->invoice->refresh()->paid_amount)->toBe(1_000_000)
        ->and(CreditLedger::balance($this->contract->id))->toBe(1_500_000);
});

it('lets the caretaker record cash they received, without the allocation section', function () {
    loginAs($this->caretaker);

    Livewire::test(RecordPayment::class)
        ->fillForm([
            'property_id' => $this->contract->property_id,
            'contract_id' => $this->contract->id,
            'method' => 'cash',
        ])
        ->assertSchemaStateSet(['received_by_user_id' => $this->caretaker->id])
        ->assertDontSee('Alokasi ke tagihan')
        ->fillForm(['amount' => '2400000'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(StaffCashBalances::class)->assertSee(['Rp2.400.000', $this->caretaker->name]);
});

it('queues a caretaker transfer and verifies it from the payment page', function () {
    Storage::fake();
    loginAs($this->caretaker);
    $payment = PaymentScenario::transfer($this->contract, 2_400_000, ['proofs' => [PaymentScenario::proof()]]);
    loginAs($this->owner);

    Livewire::test(ListPayments::class)
        ->set('activeTab', 'verifikasi')
        ->assertCanSeeTableRecords([$payment]);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee('Bukti bayar')
        ->assertActionHidden('receipt')
        ->callAction('verify', ['allocation_mode' => 'auto'])
        ->assertNotified('Pembayaran terverifikasi');

    expect($payment->refresh()->status)->toBeInstanceOf(Verified::class)
        ->and($this->invoice->refresh()->status)->toBeInstanceOf(Paid::class);
});

it('rejects and reverses from the payment page', function () {
    loginAs($this->caretaker);
    $pending = PaymentScenario::transfer($this->contract, 100_000);
    loginAs($this->owner);
    $verified = PaymentScenario::transfer($this->contract, 2_400_000);

    Livewire::test(ViewPayment::class, ['record' => $pending->getRouteKey()])
        ->callAction('reject', ['reason' => 'Dana tidak masuk'])
        ->assertNotified('Pembayaran ditolak');

    Livewire::test(ViewPayment::class, ['record' => $verified->getRouteKey()])
        ->callAction('reverse', ['reason' => 'Transfer ditolak bank'])
        ->assertNotified('Pembayaran dibalik');

    expect($pending->refresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($verified->refresh()->status)->toBeInstanceOf(Reversed::class);
});

it('hides verification and reversal from the caretaker', function () {
    loginAs($this->caretaker);
    $payment = PaymentScenario::transfer($this->contract, 100_000);

    Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()])
        ->assertActionHidden('verify')
        ->assertActionHidden('reject')
        ->assertActionHidden('reverse');

    expect($payment->status)->toBeInstanceOf(Pending::class);
});

it('serves the receipt PDF to staff and through the shared link', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);

    $this->get(route('payment.receipts.pdf', ['payment' => $payment->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $link = app(ReceiptDocument::class)->shareUrl($payment);
    tenancy()->forget();
    auth()->logout();

    $this->get($link)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('payment.receipts.shared', ['payment' => $payment->id]))->assertForbidden();
});

it('confirms a short handover with an explanation from the list', function () {
    loginAs($this->caretaker);
    PaymentScenario::cash($this->contract, 2_400_000, $this->caretaker);

    Livewire::test(ListCashHandovers::class)
        ->callAction('handOver', [
            'property_id' => $this->contract->property_id,
            'actual_amount' => '2350000',
            'destination_account_id' => Account::system(AccountSubtype::Cash)->id,
        ])
        ->assertNotified('Setoran dicatat, menunggu diterima owner');

    $handover = StaffCashHandover::query()->sole();
    loginAs($this->owner);

    Livewire::test(ListCashHandovers::class)
        ->assertSee('Kurang Rp50.000')
        ->callAction(TestAction::make('confirm')->table($handover), ['actual_amount' => '2350000'])
        ->assertNotified('Belum bisa diproses');

    Livewire::test(ListCashHandovers::class)
        ->callAction(TestAction::make('confirm')->table($handover), [
            'actual_amount' => '2350000',
            'difference_note' => 'Dipakai beli lampu lorong',
        ])
        ->assertNotified('Setoran diterima');

    expect($handover->refresh()->status)->toBeInstanceOf(Confirmed::class);
});

it('manages the deposit and credit from the contract page', function () {
    PaymentScenario::transfer($this->contract, 2_500_000);

    Livewire::test(CreditTransactionsRelationManager::class, ['ownerRecord' => $this->contract, 'pageClass' => ViewContract::class])
        ->assertSee('Saldo kredit sekarang Rp100.000');

    Livewire::test(DepositTransactionsRelationManager::class, ['ownerRecord' => $this->contract, 'pageClass' => ViewContract::class])
        ->assertSee('Deposit dipegang Rp1.200.000')
        ->callAction(TestAction::make('deduct')->table(), ['amount' => '200000', 'reason' => 'Kunci kamar hilang'])
        ->assertHasNoFormErrors()
        ->callAction(TestAction::make('refund')->table(), ['amount' => '1000000', 'account_id' => $this->bank->ledger_account_id])
        ->assertHasNoFormErrors();

    expect(DepositLedger::balance($this->contract->id))->toBe(0);
});

it('reports the deposit held per property', function () {
    PaymentScenario::transfer($this->contract, 2_400_000);

    Livewire::test(DepositsHeld::class)
        ->assertSee([$this->contract->property()->firstOrFail()->name, 'Rp1.200.000']);

    loginAs($this->caretaker);
    expect(DepositsHeld::canAccess())->toBeFalse();
});

it('keeps payment pages away from other tenants', function () {
    $payment = PaymentScenario::transfer($this->contract, 2_400_000);
    tenancy()->forget();
    loginAs(staff(Role::Owner));

    $this->get(ViewPayment::getUrl(['record' => $payment->id], panel: 'app'))->assertNotFound();
    $this->get(route('payment.receipts.pdf', ['payment' => $payment->id]))->assertNotFound();
});
