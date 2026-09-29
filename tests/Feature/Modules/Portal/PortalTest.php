<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Portal\Filament\App\Resources\Announcements\Pages\CreateAnnouncement;
use App\Modules\Portal\Livewire\InvoiceDetail;
use App\Modules\Portal\Livewire\ReportTicket;
use App\Modules\Portal\Models\Announcement;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorType;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\PaymentScenario;
use Tests\Support\PortalScenario;

beforeEach(function () {
    Storage::fake();
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->tenant = tenancy()->tenant();
    PaymentScenario::bankAccount();

    $this->contract = PortalScenario::residentContract('+6281200000001');
    $this->invoices = BillingScenario::issueDue($this->contract);
    $this->resident = PortalScenario::resident($this->contract);

    $this->neighbour = PortalScenario::residentContract('+6281200000009');
    $this->neighbourInvoices = BillingScenario::issueDue($this->neighbour);

    PortalScenario::useTenantUrls();
});

function asResident(Resident $resident): void
{
    test()->actingAs($resident, 'resident');
}

/**
 * What the portal middleware sets up, for Livewire tests that skip it.
 */
function asPortalResident(Resident $resident): void
{
    Livewire::actingAs($resident, 'resident');
    actors()->set(Actor::resident($resident));
    app()->instance(PortalAccess::class, PortalAccess::for($resident));
}

it('shows the resident what they owe and their own bills only', function () {
    asResident($this->resident);
    $own = $this->invoices[0];
    $theirs = $this->neighbourInvoices[0];

    $this->get(PortalScenario::url('portal.home'))
        ->assertOk()
        ->assertSee(['Sisa tagihan', 'Laporkan kerusakan']);

    $this->get(PortalScenario::url('portal.invoices'))
        ->assertOk()
        ->assertSee("Kamar {$this->contract->room?->number}")
        ->assertDontSee("Kamar {$this->neighbour->room?->number}");

    $this->get(PortalScenario::url('portal.invoices.show', ['invoice' => $own->id]))->assertOk()->assertSee($own->number);
    $this->get(PortalScenario::url('portal.invoices.show', ['invoice' => $theirs->id]))->assertNotFound();
    $this->get(PortalScenario::url('portal.contracts.pdf', ['contract' => $this->neighbour->id]))->assertNotFound();
    $this->get(PortalScenario::url('portal.contracts.pdf', ['contract' => $this->contract->id]))->assertOk();
});

it('sends guests to the login page', function () {
    $this->get(PortalScenario::url('portal.home'))->assertRedirect(PortalScenario::url('portal.login'));
});

it('does not carry a portal login into another tenant', function () {
    $other = Tenant::factory()->create();
    $session = [Auth::guard('resident')->getName() => $this->resident->id];

    $this->withSession($session)->get(PortalScenario::url('portal.home'))->assertOk();
    $this->withSession($session)->get(route('portal.home', ['tenant' => $other->slug]))->assertRedirect(route('portal.login', ['tenant' => $other->slug]));
});

it('shows a parent who pays only the bills, not repairs or announcements', function () {
    $contract = PortalScenario::parentPaidContract('+6281300000002');
    $invoices = BillingScenario::issueDue($contract);
    $this->actingAs(PortalScenario::payer($contract), 'payer');

    $this->get(PortalScenario::url('portal.invoices.show', ['invoice' => $invoices[0]->id]))->assertOk();
    $this->get(PortalScenario::url('portal.invoices.show', ['invoice' => $this->invoices[0]->id]))->assertNotFound();
    $this->get(PortalScenario::url('portal.tickets'))->assertNotFound();
    $this->get(PortalScenario::url('portal.announcements'))->assertNotFound();
    $this->get(PortalScenario::url('portal.account'))->assertOk()->assertSee('Masuk sebagai pembayar');
});

it('takes a transfer proof into the verification queue and tells the verifiers', function () {
    asPortalResident($this->resident);
    // Livewire drops temporary uploads older than a day by the app clock.
    $this->travelBack();
    $invoice = $this->invoices[0]->refresh();

    Livewire::test(InvoiceDetail::class, ['invoice' => $invoice->id])
        ->assertSet('amount', (string) $invoice->balance_amount)
        ->set('proof', UploadedFile::fake()->image('bukti.jpg'))
        ->set('note', 'Dari rekening kakak')
        ->call('submitProof')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('Sedang diperiksa pengelola');

    $payment = Payment::query()->sole();

    expect($payment->status)->toBeInstanceOf(Pending::class)
        ->and($payment->channel)->toBe(PaymentChannel::Portal)
        ->and($payment->amount)->toBe($invoice->balance_amount)
        ->and($payment->reference)->toBe('Dari rekening kakak')
        ->and(Attachment::query()->where('attachable_id', $payment->id)->count())->toBe(1)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->owner->id)->where('data->title', 'like', 'Bukti transfer%')->exists())->toBeTrue()
        ->and(Invoice::query()->whereKey($invoice->id)->value('balance_amount'))->toBe($invoice->balance_amount);
});

it('reports a repair from the portal and keeps it from other rooms', function () {
    asPortalResident($this->resident);
    // Livewire drops temporary uploads older than a day by the app clock.
    $this->travelBack();

    Livewire::test(ReportTicket::class)
        ->set('category', 'plumbing')
        ->set('title', 'Keran kamar mandi bocor')
        ->set('description', 'Menetes terus sejak semalam.')
        ->set('photos', [UploadedFile::fake()->image('keran.jpg')])
        ->call('submit')
        ->assertHasNoErrors();

    $ticket = Ticket::query()->sole();

    expect($ticket->room_id)->toBe($this->contract->room_id)
        ->and($ticket->reported_by_type)->toBe(ActorType::Resident)
        ->and($ticket->reported_by_id)->toBe($this->resident->id)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->owner->id)->where('data->title', 'like', 'Laporan penghuni%')->exists())->toBeTrue();

    asResident($this->resident);
    $this->get(PortalScenario::url('portal.tickets.show', ['ticket' => $ticket->id]))->assertOk()->assertSee(['Keran kamar mandi bocor', 'Baru']);

    asResident(PortalScenario::resident($this->neighbour));
    $this->get(PortalScenario::url('portal.tickets'))->assertOk()->assertDontSee('Keran kamar mandi bocor');
    $this->get(PortalScenario::url('portal.tickets.show', ['ticket' => $ticket->id]))->assertNotFound();
});

it('shows published announcements of the resident\'s own property', function () {
    Announcement::factory()->create(['property_id' => $this->contract->property_id, 'title' => 'Air mati Sabtu']);
    Announcement::factory()->draft()->create(['property_id' => $this->contract->property_id, 'title' => 'Draf rahasia']);
    Announcement::factory()->create(['property_id' => $this->neighbour->property_id, 'title' => 'Kost sebelah']);

    asResident($this->resident);

    $this->get(PortalScenario::url('portal.announcements'))
        ->assertOk()
        ->assertSee('Air mati Sabtu')
        ->assertDontSee(['Draf rahasia', 'Kost sebelah']);
});

it('can be installed on a phone', function () {
    $this->get(PortalScenario::url('portal.manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', $this->tenant->name)
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('icons.0.sizes', '192x192');

    $this->get(PortalScenario::url('portal.service-worker'))->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    $this->get(PortalScenario::url('portal.icon', ['size' => 512]))->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get(PortalScenario::url('portal.icon', ['size' => 64]))->assertNotFound();
    $this->get(PortalScenario::url('portal.offline'))->assertOk()->assertSee('Tidak ada koneksi internet');
});

it('closes the portal of a frozen tenant', function () {
    $this->tenant->forceFill(['frozen_at' => now()])->save();

    $this->get(PortalScenario::url('portal.login'))->assertNotFound();
});

it('lets owners and managers write announcements for their properties', function () {
    Livewire::test(CreateAnnouncement::class)
        ->fillForm(['property_id' => $this->contract->property_id, 'title' => 'Jadwal kebersihan', 'body' => 'Kamar dibersihkan tiap Senin.', 'publish' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Announcement::query()->sole())
        ->title->toBe('Jadwal kebersihan')
        ->created_by->toBe($this->owner->id)
        ->published_at->not->toBeNull();

    loginAs(staff(Role::Caretaker, $this->tenant));
    $this->get('/app/pengumuman')->assertForbidden();
});

it('keeps the neighbour\'s contract out of the resident\'s account page', function () {
    asResident($this->resident);

    $this->get(PortalScenario::url('portal.account'))
        ->assertOk()
        ->assertSee($this->contract->number)
        ->assertDontSee($this->neighbour->number);
});
