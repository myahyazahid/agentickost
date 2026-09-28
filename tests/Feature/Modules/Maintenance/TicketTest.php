<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Maintenance\Actions\AssignTicket;
use App\Modules\Maintenance\Actions\CommentOnTicket;
use App\Modules\Maintenance\Actions\ConfirmTicket;
use App\Modules\Maintenance\Actions\RejectTicket;
use App\Modules\Maintenance\Actions\ReopenTicket;
use App\Modules\Maintenance\Actions\ReportTicket;
use App\Modules\Maintenance\Actions\ResolveTicket;
use App\Modules\Maintenance\Actions\StartTicketWork;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\AwaitingConfirmation;
use App\Modules\Maintenance\States\Ticket\Done;
use App\Modules\Maintenance\States\Ticket\InProgress;
use App\Modules\Maintenance\States\Ticket\Rejected;
use App\Modules\Maintenance\States\Ticket\Reported;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Tenancy\Support\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\LedgerScenario;
use Tests\Support\PaymentScenario;

/*
 * Roadmap M1.7 done criterion: staff report a ticket, it is resolved, and
 * its cost shows up as an expense or on the resident's bill.
 */
beforeEach(function () {
    $this->travelTo('2026-09-20 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->room = LeaseScenario::room();
    $this->property = $this->room->property()->firstOrFail();
    $this->caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $this->caretaker);
});

function photoAt(AttachmentCollection $collection): string
{
    $path = app(TenantStorage::class)->path($collection->directory().'/'.fake()->uuid().'.jpg');
    Storage::put($path, 'jpeg-bytes');

    return $path;
}

function reportLeak(array $overrides = []): Ticket
{
    return app(ReportTicket::class)->handle(test()->property, [
        'room_id' => test()->room->id,
        'category' => 'plumbing',
        'priority' => 'high',
        'title' => 'Keran kamar mandi bocor',
        'description' => 'Air menetes terus sejak pagi.',
        ...$overrides,
    ]);
}

it('runs a ticket from report to confirmation and books its cost as an expense', function () {
    Storage::fake();
    loginAs($this->caretaker);
    $ticket = reportLeak(['photos' => [photoAt(AttachmentCollection::Before)]]);

    expect($ticket->status)->toBeInstanceOf(Reported::class)
        ->and($ticket->attachmentPaths(AttachmentCollection::Before))->toHaveCount(1);

    loginAs($this->owner);
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->caretaker->id]);

    loginAs($this->caretaker);
    app(StartTicketWork::class)->handle($ticket);
    app(ResolveTicket::class)->handle($ticket, [
        'note' => 'Ganti karet keran',
        'cost_amount' => 0,
        'photos' => [photoAt(AttachmentCollection::After)],
    ]);

    expect($ticket->refresh()->status)->toBeInstanceOf(AwaitingConfirmation::class)
        ->and($ticket->attachmentPaths(AttachmentCollection::After))->toHaveCount(1);

    loginAs($this->owner);
    app(ReopenTicket::class)->handle($ticket, ['reason' => 'Masih menetes sedikit']);
    expect($ticket->refresh()->status)->toBeInstanceOf(InProgress::class);

    app(ResolveTicket::class)->handle($ticket, [
        'note' => 'Keran diganti baru',
        'cost_amount' => 185_000,
        'paid_from_account_id' => Account::system(AccountSubtype::Cash)->id,
    ]);
    app(ConfirmTicket::class)->handle($ticket);

    $ticket->refresh();
    $expense = Expense::query()->sole();

    expect($ticket->status)->toBeInstanceOf(Done::class)
        ->and($ticket->confirmed_at)->not->toBeNull()
        ->and($ticket->expense_id)->toBe($expense->id)
        ->and($expense->amount)->toBe(185_000)
        ->and($expense->expense_account_id)->toBe(Account::system(AccountSubtype::MaintenanceExpense)->id)
        ->and($expense->ticket_id)->toBe($ticket->id)
        ->and(LedgerScenario::balance(AccountSubtype::MaintenanceExpense))->toBe(185_000)
        ->and($ticket->updates()->pluck('to_status')->filter()->values()->all())
        ->toBe(['new', 'assigned', 'in_progress', 'awaiting_confirmation', 'in_progress', 'awaiting_confirmation', 'done']);
});

it('bills the resident for damage they caused', function () {
    $contract = LeaseScenario::active($this->room);
    $ticket = reportLeak(['title' => 'Pintu lemari patah', 'category' => 'furniture']);
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->caretaker->id]);
    app(StartTicketWork::class)->handle($ticket);
    app(ResolveTicket::class)->handle($ticket, [
        'note' => 'Engsel dan pintu diganti',
        'cost_amount' => 250_000,
        'paid_from_account_id' => PaymentScenario::bankAccount()->ledger_account_id,
        'charge_to_resident' => true,
    ]);

    app(ConfirmTicket::class)->handle($ticket);

    $invoice = Invoice::query()->findOrFail($ticket->refresh()->charge_invoice_id);

    expect($invoice->contract_id)->toBe($contract->id)
        ->and($invoice->status)->toBeInstanceOf(Issued::class)
        ->and(BillingScenario::lines($invoice))->toBe([['damage', 250_000]])
        ->and($invoice->items()->value('source_id'))->toBe($ticket->id)
        ->and(LedgerScenario::balance(AccountSubtype::OtherRevenue))->toBe(250_000)
        ->and(LedgerScenario::balance(AccountSubtype::MaintenanceExpense))->toBe(250_000);
});

it('lets the caretaker pay a repair from the cash they hold', function () {
    $contract = LeaseScenario::active($this->room);
    BillingScenario::issueDue($contract);
    loginAs($this->caretaker);
    PaymentScenario::cash($contract, 500_000, $this->caretaker);
    $ownCash = Account::query()->where('user_id', $this->caretaker->id)->sole();
    $ticket = reportLeak();
    loginAs($this->owner);
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->caretaker->id]);
    loginAs($this->caretaker);
    app(StartTicketWork::class)->handle($ticket);

    expect(fn () => app(ResolveTicket::class)->handle($ticket, [
        'note' => 'Keran diganti', 'cost_amount' => 60_000, 'paid_from_account_id' => Account::system(AccountSubtype::Cash)->id,
    ]))->toThrow(ValidationException::class, 'kas atau rekening');

    app(ResolveTicket::class)->handle($ticket, ['note' => 'Keran diganti', 'cost_amount' => 60_000, 'paid_from_account_id' => $ownCash->id]);
    loginAs($this->owner);
    app(ConfirmTicket::class)->handle($ticket);

    expect(StaffCash::balance($this->caretaker->id, $this->property->id))->toBe(440_000);
});

it('refuses to bill a resident for a common area or an empty room', function () {
    $ticket = reportLeak(['room_id' => null, 'title' => 'Lampu lorong mati', 'category' => 'electrical']);
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->owner->id]);
    app(StartTicketWork::class)->handle($ticket);

    app(ResolveTicket::class)->handle($ticket, [
        'note' => 'Lampu diganti', 'cost_amount' => 40_000,
        'paid_from_account_id' => Account::system(AccountSubtype::Cash)->id, 'charge_to_resident' => true,
    ]);
})->throws(ValidationException::class, 'kontrak berjalan');

it('rejects a new ticket with a reason and keeps it from being worked on', function () {
    $ticket = reportLeak();

    app(RejectTicket::class)->handle($ticket, ['reason' => 'Sudah dilaporkan di tiket lain']);

    expect($ticket->refresh()->status)->toBeInstanceOf(Rejected::class)
        ->and($ticket->updates()->get()->last()?->note)->toBe('Sudah dilaporkan di tiket lain')
        ->and(fn () => app(StartTicketWork::class)->handle($ticket))->toThrow(ValidationException::class);
});

it('lets only the assignee or a manager work on a ticket, and only managers confirm', function () {
    $other = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $other);
    $ticket = reportLeak();
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->caretaker->id]);

    loginAs($other);
    expect(fn () => app(StartTicketWork::class)->handle($ticket))->toThrow(AuthorizationException::class);
    app(CommentOnTicket::class)->handle($ticket, ['note' => 'Saya lihat airnya sampai lorong']);

    loginAs($this->caretaker);
    app(StartTicketWork::class)->handle($ticket);
    app(ResolveTicket::class)->handle($ticket, ['note' => 'Sudah diperbaiki']);

    expect(fn () => app(ConfirmTicket::class)->handle($ticket))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $other->id]))->toThrow(AuthorizationException::class);
});

it('assigns only staff of the property and hands work over while in progress', function () {
    $outsider = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    $ticket = reportLeak();

    expect(fn () => app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $outsider->id]))
        ->toThrow(ValidationException::class, 'bertugas di properti ini');

    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->caretaker->id]);
    app(StartTicketWork::class)->handle($ticket);
    app(AssignTicket::class)->handle($ticket, ['assigned_user_id' => $this->owner->id]);

    expect($ticket->refresh()->assigned_user_id)->toBe($this->owner->id)
        ->and($ticket->status)->toBeInstanceOf(InProgress::class);
});
