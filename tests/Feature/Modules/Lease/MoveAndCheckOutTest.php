<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Actions\FinalizeSettlement;
use App\Modules\Lease\Actions\GiveNotice;
use App\Modules\Lease\Actions\MoveRoom;
use App\Modules\Lease\Actions\RecordCheckOut;
use App\Modules\Lease\Models\RoomMove;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Settlement\Finalized;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\States\Room\Occupied;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;
use Tests\Support\PaymentScenario;

/**
 * Roadmap M1.6 done criterion: a mid-month room move and a check-out with a
 * deposit deduction, with their journals.
 *
 * Room A rents for 1.200.000 with a 1.200.000 deposit from 1 September.
 * On 16 September the resident moves to room B at 1.500.000 with a
 * 1.500.000 deposit. September has 30 days, so 15 days are left.
 */
beforeEach(function () {
    $this->travelTo('2026-09-01 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->roomA = LeaseScenario::room();
    $this->roomB = Room::factory()->forType($this->roomA->roomType()->firstOrFail())->create(['number' => 'B1', 'capacity' => 1]);
    app(SetRoomPrice::class)->handle($this->roomB, ['rental_period' => 'monthly', 'amount' => 1_500_000, 'effective_from' => '2026-01-01']);
    $this->contract = LeaseScenario::active($this->roomA, [
        'start_date' => '2026-09-01',
        'early_termination_penalty_amount' => 500_000,
    ]);
    [$this->september] = BillingScenario::issueDue($this->contract);
    PaymentScenario::transfer($this->contract, 2_400_000);
});

afterEach(function () {
    expect(LedgerScenario::unbalancedEntries())->toBe([]);
});

function moveToB(): RoomMove
{
    test()->travelTo('2026-09-16 03:00:00');

    return app(MoveRoom::class)->handle(test()->contract, [
        'to_room_id' => test()->roomB->id,
        'moved_on' => '2026-09-16',
        'new_deposit_amount' => 1_500_000,
    ]);
}

it('moves mid-month: the old room is charged to the day before, the paid rest becomes credit, the new room is billed from the move', function () {
    $move = moveToB();

    $credit = CreditNote::query()->where('room_move_id', $move->id)->sole();
    $moveInvoice = Invoice::query()->findOrFail($move->invoice_id);

    expect($credit->amount)->toBe(600_000)
        ->and($credit->invoice_id)->toBe($this->september->id)
        ->and($this->september->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and(BillingScenario::lines($moveInvoice))->toBe([['rent', 750_000], ['deposit', 300_000]])
        ->and($moveInvoice->type)->toBe(InvoiceType::Adhoc)
        // The 600.000 freed from September pays the move invoice at once, deposit first.
        ->and($moveInvoice->paid_amount)->toBe(600_000)
        ->and($moveInvoice->refresh()->balance_amount)->toBe(450_000)
        ->and(CreditLedger::balance($this->contract->id))->toBe(0)
        ->and(DepositLedger::balance($this->contract->id))->toBe(1_500_000);

    $contract = $this->contract->refresh();

    expect($contract->room_id)->toBe($this->roomB->id)
        ->and($contract->rent_amount)->toBe(1_500_000)
        ->and($contract->deposit_amount)->toBe(1_500_000)
        ->and($this->roomA->refresh()->status)->toBeInstanceOf(Available::class)
        ->and($this->roomB->refresh()->status)->toBeInstanceOf(Occupied::class)
        ->and($move->old_rent_amount)->toBe(1_200_000)
        ->and($move->deposit_difference_amount)->toBe(300_000);
});

it('books the move in the ledger: rent earned for each room for its own days', function () {
    moveToB();

    expect(LedgerScenario::balance(AccountSubtype::RentRevenue))->toBe(1_350_000)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable, $this->contract->id))->toBe(450_000)
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability, $this->contract->id))->toBe(1_500_000)
        ->and(LedgerScenario::balance(AccountSubtype::CreditLiability, $this->contract->id))->toBe(0)
        ->and(JournalEntry::query()->where('event', JournalEvent::CreditNoteIssued->value)->count())->toBe(1)
        ->and(JournalEntry::query()->where('event', JournalEvent::AllocationReleased->value)->count())->toBe(1);
});

it('bills the next month at the new room and rent', function () {
    moveToB();
    $this->travelTo('2026-09-28 03:00:00');

    [$october] = BillingScenario::issueDue($this->contract->refresh());

    expect(BillingScenario::lines($october))->toBe([['rent', 1_500_000]])
        ->and($october->items()->value('description'))->toContain('kamar B1');
});

it('checks out with damage and a short-notice penalty taken from the deposit, and pays back the rest', function () {
    moveToB();
    PaymentScenario::transfer($this->contract, 450_000);
    $this->travelTo('2026-09-28 03:00:00');
    BillingScenario::issueDue($this->contract->refresh());
    PaymentScenario::transfer($this->contract, 1_500_000);
    $this->travelTo('2026-10-05 03:00:00');
    app(GiveNotice::class)->handle($this->contract->refresh(), ['planned_move_out_on' => '2026-10-31']);
    $this->travelTo('2026-10-31 03:00:00');

    $settlement = app(RecordCheckOut::class)->handle($this->contract->refresh(), [
        'moved_out_on' => '2026-10-31',
        'room_after' => 'maintenance',
        'items' => [
            ['item_name' => 'Kasur dan dipan', 'condition' => 'good'],
            ['item_name' => 'Kunci', 'condition' => 'missing', 'charge_amount' => 250_000],
        ],
    ]);

    // Notice of 26 days against 30 proposes the contract's penalty.
    expect($settlement->early_termination_amount)->toBe(500_000)
        ->and($settlement->damage_amount)->toBe(250_000)
        ->and($settlement->result_amount)->toBe(-750_000);

    $bank = PaymentScenario::bankAccount()->ledger_account_id;
    $bankBefore = LedgerScenario::account($bank);

    app(FinalizeSettlement::class)->handle($settlement, ['refund_account_id' => $bank]);

    $settlement->refresh();
    $contract = $this->contract->refresh();

    expect($settlement->status)->toBeInstanceOf(Finalized::class)
        ->and($settlement->outstanding_amount)->toBe(0)
        ->and($settlement->deposit_balance_amount)->toBe(1_500_000)
        ->and($settlement->result_amount)->toBe(-750_000)
        ->and(BillingScenario::lines(Invoice::query()->findOrFail($settlement->final_invoice_id)))->toBe([['damage', 250_000], ['other', 500_000]])
        ->and(Invoice::query()->findOrFail($settlement->final_invoice_id)->status)->toBeInstanceOf(Paid::class)
        ->and(DepositLedger::balance($contract->id))->toBe(0)
        ->and($contract->status)->toBeInstanceOf(Completed::class)
        ->and($contract->ended_on->toDateString())->toBe('2026-10-31')
        ->and($contract->occupants()->whereNull('left_on')->count())->toBe(0)
        ->and($this->roomB->refresh()->status)->toBeInstanceOf(Maintenance::class)
        // Journals: deposit paid the final invoice and the rest left the bank.
        ->and(LedgerScenario::balance(AccountSubtype::DepositLiability))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::Receivable))->toBe(0)
        ->and(LedgerScenario::balance(AccountSubtype::OtherRevenue))->toBe(750_000)
        ->and(LedgerScenario::account($bank))->toBe($bankBefore - 750_000);
});

it('leaves what the deposit cannot cover owed on the open invoices', function () {
    $this->travelTo('2026-09-10 03:00:00');
    app(GiveNotice::class)->handle($this->contract, ['planned_move_out_on' => '2026-09-20']);
    $this->travelTo('2026-09-20 03:00:00');

    $settlement = app(RecordCheckOut::class)->handle($this->contract->refresh(), [
        'moved_out_on' => '2026-09-20',
        'room_after' => 'available',
        'early_termination_amount' => 0,
        'items' => [['item_name' => 'Lemari', 'condition' => 'damaged', 'charge_amount' => 1_500_000]],
    ]);

    app(FinalizeSettlement::class)->handle($settlement);

    expect($settlement->refresh()->result_amount)->toBe(300_000)
        ->and(DepositLedger::balance($this->contract->id))->toBe(0)
        ->and(Invoice::query()->where('contract_id', $this->contract->id)->sum('balance_amount'))->toEqual(300_000)
        ->and($this->roomA->refresh()->status)->toBeInstanceOf(Available::class);
});
