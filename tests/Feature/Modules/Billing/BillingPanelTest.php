<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Filament\App\Pages\UpcomingInvoices;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\CreateAdhocInvoice;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\EditDraftInvoice;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Modules\Billing\Filament\App\Resources\MeterReadings\Pages\RecordMeterReading;
use App\Modules\Billing\Filament\App\Resources\UtilityRates\Pages\ManageUtilityRates;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Billing\States\Invoice\Issued;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Billing\Support\InvoiceDocument;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Filament\App\Pages\DocumentNumbering;
use App\Modules\Documents\Models\DocumentSequence;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Filament\App\Resources\Rooms\Pages\ListRooms;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-15 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->contract = LeaseScenario::active(overrides: ['deposit_amount' => 0]);
});

it('lists invoices and marks the late ones', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-09-25 03:00:00');

    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$invoice])
        ->assertSee('Telat 5 hari')
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$invoice]);
});

it('creates a manual invoice from the form and issues it from its page', function () {
    Livewire::test(CreateAdhocInvoice::class)
        ->fillForm([
            'property_id' => $this->contract->property_id,
            'contract_id' => $this->contract->id,
            'due_date' => '2026-09-30',
            'items' => [['type' => 'damage', 'description' => 'Ganti kunci', 'amount' => 150_000]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->sole();
    expect(BillingScenario::lines($invoice))->toBe([['damage', 150_000]]);

    $edit = Livewire::test(EditDraftInvoice::class, ['record' => $invoice->getRouteKey()]);
    expect(array_column($edit->get('data.items'), 'amount'))->toBe([150_000]);

    $edit->fillForm(['items' => [['type' => 'damage', 'description' => 'Ganti kunci dan gembok', 'amount' => 175_000]]])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('issue')
        ->assertNotified('Tagihan terbit');

    expect($invoice->refresh()->status)->toBeInstanceOf(Issued::class)
        ->and($invoice->items_total_amount)->toBe(175_000);
});

it('voids, credits, and waives from the invoice page', function () {
    BillingScenario::settings($this->contract->property()->firstOrFail(), ['penalty_type' => 'flat', 'penalty_amount' => 50_000]);
    [$first] = BillingScenario::issueDue($this->contract);

    Livewire::test(ViewInvoice::class, ['record' => $first->getRouteKey()])
        ->callAction('void', ['reason' => 'Salah harga sewa'])
        ->assertNotified('Tagihan dibatalkan');
    expect($first->refresh()->status)->toBeInstanceOf(Voided::class);

    [$second] = BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-09-25 03:00:00');
    app(AccrueInvoicePenalties::class)->handle($second);

    Livewire::test(ViewInvoice::class, ['record' => $second->getRouteKey()])
        ->callAction('waivePenalties', ['reason' => 'Transfer tertahan di bank'])
        ->assertNotified('Denda dihapus')
        ->callAction('credit', ['allocation_category' => 'rent', 'amount' => 100_000, 'reason' => 'Air mati tiga hari'])
        ->assertNotified('Nota kredit dibuat');

    expect($second->refresh()->balance_amount)->toBe(1_100_000);
});

it('hides corrections from a manager and shows a share link', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);
    $manager = staff(Role::Manager, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->contract->property()->firstOrFail(), $manager);
    loginAs($manager);

    Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertActionHidden('void')
        ->assertActionHidden('credit')
        ->assertActionHidden('waivePenalties')
        ->mountAction('share')
        ->assertSchemaStateSet(['url' => app(InvoiceDocument::class)->shareUrl($invoice)], 'mountedActionSchema0');
});

it('previews upcoming invoices and issues the due ones', function () {
    $later = LeaseScenario::active(overrides: ['start_date' => '2026-09-27', 'deposit_amount' => 500_000]);

    Livewire::test(UpcomingInvoices::class)
        ->assertSee('Sudah waktunya')
        ->assertSee(['20 Sep sampai 19 Okt 2026', '27 Sep sampai 26 Okt 2026'])
        ->callAction(TestAction::make('issueDue')->table())
        ->assertNotified('1 tagihan terbit');

    expect(Invoice::query()->where('contract_id', $this->contract->id)->count())->toBe(1)
        ->and(Invoice::query()->where('contract_id', $later->id)->count())->toBe(0);
});

it('sets a utility rate and records a reading with a photo', function () {
    Storage::fake();
    $room = $this->contract->room()->firstOrFail();

    Livewire::test(ManageUtilityRates::class)
        ->callAction('setRate', [
            'property_id' => $room->property_id,
            'utility' => 'electricity',
            'mode' => 'metered',
            'unit' => 'kwh',
            'rate_amount' => 1_500,
            'effective_from' => '2026-01-01',
        ])
        ->assertNotified('Tarif disimpan');
    expect(UtilityRate::query()->sole()->rate_amount)->toBe(1_500);

    Livewire::test(RecordMeterReading::class)
        ->fillForm([
            'property_id' => $room->property_id,
            'room_id' => $room->id,
            'utility' => 'electricity',
            'reading_date' => '2026-09-15',
        ])
        ->assertSee('Kamar ini belum punya catatan')
        ->fillForm(['current_value' => '1200.5', 'photos' => [UploadedFile::fake()->image('meteran.jpg')]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(MeterReading::query()->sole()->current_value)->toBe('1200.50');
    Livewire::test(ListMeterReadings::class)->assertSee('Angka awal');
});

it('shows arrears on the room grid', function () {
    BillingScenario::issueDue($this->contract);
    $this->travelTo('2026-09-25 03:00:00');

    Livewire::test(ListRooms::class)->assertSee('Tunggakan Rp1.200.000');
});

it('changes the invoice number format for the owner only', function () {
    Livewire::test(DocumentNumbering::class)
        ->assertSee('INV/2026/09/0001')
        ->callAction(TestAction::make('edit_invoice')->schemaComponent('numbering_invoice', 'content'), [
            'format' => '{PROP}-{YY}{MM}-{SEQ:3}',
            'reset_period' => 'monthly',
        ])
        ->assertNotified('Format nomor disimpan');

    expect(DocumentSequence::query()->where('document_type', DocumentType::Invoice->value)->sole()->format)->toBe('{PROP}-{YY}{MM}-{SEQ:3}');

    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));
    expect(DocumentNumbering::canAccess())->toBeFalse();
});

it('keeps billing pages away from other tenants', function () {
    [$invoice] = BillingScenario::issueDue($this->contract);
    tenancy()->forget();
    $outsider = staff(Role::Owner);
    loginAs($outsider);

    $this->get(ViewInvoice::getUrl(['record' => $invoice->id], panel: 'app'))->assertNotFound();
});
