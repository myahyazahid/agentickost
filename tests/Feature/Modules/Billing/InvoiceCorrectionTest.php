<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\CreateAdhocInvoice;
use App\Modules\Billing\Actions\DeleteDraftInvoice;
use App\Modules\Billing\Actions\IssueCreditNote;
use App\Modules\Billing\Actions\IssueInvoice;
use App\Modules\Billing\Actions\UpdateDraftInvoice;
use App\Modules\Billing\Actions\VoidInvoice;
use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Events\InvoiceVoided;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Paid;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Lease\Models\Contract;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active(overrides: ['deposit_amount' => 0]);
});

function damageInvoice(Contract $contract, array $overrides = []): Invoice
{
    return app(CreateAdhocInvoice::class)->handle($contract->property()->firstOrFail(), [
        'contract_id' => $contract->id,
        'due_date' => '2026-09-30',
        'items' => [
            ['type' => 'damage', 'description' => 'Ganti kunci kamar', 'amount' => 150_000],
            ['type' => 'discount', 'description' => 'Potongan', 'amount' => 25_000],
        ],
        ...$overrides,
    ]);
}

it('drafts a manual invoice that can be edited, then issued with a number', function () {
    $invoice = damageInvoice($this->contract);

    expect($invoice->status)->toBeInstanceOf(Draft::class)
        ->and($invoice->number)->toBeNull()
        ->and(BillingScenario::lines($invoice))->toBe([['damage', 150_000], ['discount', -25_000]]);

    app(UpdateDraftInvoice::class)->handle($invoice, [
        'due_date' => '2026-10-05',
        'items' => [['type' => 'damage', 'description' => 'Ganti kunci dan gembok', 'amount' => 175_000]],
    ]);
    app(IssueInvoice::class)->handle($invoice);

    expect($invoice->refresh()->status)->toBeInstanceOf(Issued::class)
        ->and($invoice->number)->toBe('INV/2026/09/0001')
        ->and($invoice->due_date->toDateString())->toBe('2026-10-05')
        ->and($invoice->balance_amount)->toBe(175_000)
        ->and($invoice->payer_id)->toBe($this->contract->payer_id);
});

it('issues a manual invoice right away when asked', function () {
    expect(damageInvoice($this->contract, ['issue_now' => true])->status)->toBeInstanceOf(Issued::class);
});

it('deletes drafts but never an issued invoice', function () {
    $draft = damageInvoice($this->contract);
    $issued = damageInvoice($this->contract, ['issue_now' => true]);

    app(DeleteDraftInvoice::class)->handle($draft);

    expect(Invoice::query()->pluck('id')->all())->toBe([$issued->id])
        ->and(fn () => app(DeleteDraftInvoice::class)->handle($issued))->toThrow(ValidationException::class, 'Batalkan dengan void');
});

it('refuses to change an issued invoice or its lines', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);

    expect(fn () => $invoice->update(['due_date' => '2026-12-01']))->toThrow(LogicException::class)
        ->and(fn () => $invoice->items()->firstOrFail()->update(['amount' => 1]))->toThrow(LogicException::class)
        ->and(fn () => InvoiceItem::query()->where('invoice_id', $invoice->id)->firstOrFail()->delete())->toThrow(LogicException::class)
        ->and(fn () => app(UpdateDraftInvoice::class)->handle($invoice, ['due_date' => '2026-12-01', 'items' => []]))
        ->toThrow(ValidationException::class, 'sudah terbit');
});

it('keeps manual invoices from the caretaker', function () {
    loginAs(staff(Role::Caretaker, $this->owner->tenant()->firstOrFail()));

    damageInvoice($this->contract);
})->throws(AuthorizationException::class);

it('voids an unpaid rent invoice and issues the period again on the next run', function () {
    Event::fake([InvoiceVoided::class]);
    [$invoice] = BillingScenario::issueDue($this->contract);

    app(VoidInvoice::class)->handle($invoice, ['reason' => 'Salah harga sewa']);

    expect($invoice->refresh()->status)->toBeInstanceOf(Voided::class)
        ->and($invoice->void_reason)->toBe('Salah harga sewa')
        ->and($invoice->generation_key)->toBeNull()
        ->and($this->contract->refresh()->next_period_start->toDateString())->toBe('2026-09-20');
    Event::assertDispatched(InvoiceVoided::class);

    [$again] = BillingScenario::issueDue($this->contract);

    expect($again->id)->not->toBe($invoice->id)
        ->and($again->period_start->toDateString())->toBe('2026-09-20')
        ->and($again->number)->toBe('INV/2026/09/0002');
});

it('frees the meter readings of a voided invoice for the next one', function () {
    Storage::fake();
    $room = $this->contract->room()->firstOrFail();
    BillingScenario::meteredElectricity($room->property()->firstOrFail());
    BillingScenario::reading($room, '2026-09-14', 1_000);
    $this->travelTo('2026-10-13 03:00:00');
    BillingScenario::issueDue($this->contract);
    $reading = BillingScenario::reading($room, '2026-10-13', 1_100);
    $this->travelTo('2026-11-13 03:00:00');
    [$invoice] = BillingScenario::issueDue($this->contract);

    app(VoidInvoice::class)->handle($invoice, ['reason' => 'Angka meteran salah ketik']);

    expect($reading->refresh()->isBilled())->toBeFalse();
});

it('voids only invoices without payments or credit notes, and only for the owner', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);

    expect(fn () => app(VoidInvoice::class)->handle($invoice, ['reason' => 'x']))->toThrow(ValidationException::class);

    $invoice->paid_amount = 100_000;
    $invoice->save();

    expect(fn () => app(VoidInvoice::class)->handle($invoice, ['reason' => 'Penghuni batal masuk']))
        ->toThrow(ValidationException::class, 'buat nota kredit');

    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    expect(fn () => app(VoidInvoice::class)->handle($invoice, ['reason' => 'Penghuni batal masuk']))
        ->toThrow(AuthorizationException::class);
});

it('credits part of an invoice with a numbered credit note', function () {
    Event::fake([CreditNoteIssued::class]);
    [$invoice] = BillingScenario::issueDue($this->contract);

    $note = app(IssueCreditNote::class)->handle($invoice, [
        'allocation_category' => 'rent', 'amount' => 200_000, 'reason' => 'Kamar mati air 5 hari',
    ]);

    expect($note->number)->toBe('NK/2026/0001')
        ->and($invoice->refresh()->credited_amount)->toBe(200_000)
        ->and($invoice->balance_amount)->toBe(1_000_000)
        ->and(fn () => $note->update(['amount' => 1]))->toThrow(LogicException::class);
    Event::assertDispatched(CreditNoteIssued::class);
});

it('settles an invoice credited in full, and caps credit notes per category', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);

    expect(fn () => app(IssueCreditNote::class)->handle($invoice, [
        'allocation_category' => 'utility', 'amount' => 1, 'reason' => 'Tidak ada utilitas',
    ]))->toThrow(ValidationException::class, 'paling banyak Rp0');

    app(IssueCreditNote::class)->handle($invoice, [
        'allocation_category' => 'rent', 'amount' => 1_200_000, 'reason' => 'Gratis bulan pertama',
    ]);

    expect($invoice->refresh()->status)->toBeInstanceOf(Paid::class)
        ->and($invoice->balance_amount)->toBe(0);
});

it('credits only issued invoices and only as the owner', function () {
    $draft = damageInvoice($this->contract);

    expect(fn () => app(IssueCreditNote::class)->handle($draft, [
        'allocation_category' => 'other', 'amount' => 1_000, 'reason' => 'Coba kredit draf',
    ]))->toThrow(ValidationException::class, 'sudah terbit');

    [$invoice] = BillingScenario::issueDue($this->contract);
    $manager = staff(Role::Manager, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contract->property()->firstOrFail(), $manager);
    loginAs($manager);

    app(IssueCreditNote::class)->handle($invoice, [
        'allocation_category' => 'rent', 'amount' => 1_000, 'reason' => 'Coba kredit manajer',
    ]);
})->throws(AuthorizationException::class);
