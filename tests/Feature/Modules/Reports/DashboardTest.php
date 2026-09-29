<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Payment\Models\Payment;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Reports\Filament\App\Pages\Arrears;
use App\Modules\Reports\Filament\App\Widgets\BusinessOverview;
use App\Modules\Reports\Support\DashboardFigures;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\BillingScenario;
use Tests\Support\LeaseScenario;
use Tests\Support\PaymentScenario;

/*
 * On 5 October: contract A (from 20 September) has its first invoice of
 * Rp2.400.000 (rent and deposit) overdue since 20 September and Rp1.000.000
 * paid; contract B (from 10 October) has its first invoice issued but not yet
 * due. Three rooms, two occupied.
 */
beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));

    $roomA = LeaseScenario::room();
    $roomB = Room::factory()->forType($roomA->roomType()->firstOrFail())->create();
    Room::factory()->forType($roomA->roomType()->firstOrFail())->create();
    $this->property = $roomA->property()->firstOrFail();

    $this->contractA = LeaseScenario::active($roomA);
    $this->contractB = LeaseScenario::active($roomB, ['start_date' => '2026-10-10']);
    BillingScenario::issueDue($this->contractA);
    BillingScenario::issueDue($this->contractB);

    PaymentScenario::transfer($this->contractA, 1_000_000);
    Payment::factory()->create(['contract_id' => $this->contractB->id, 'amount' => 500_000]);

    Ticket::factory()->create(['property_id' => $this->property->id, 'priority' => TicketPriority::Urgent]);
    Ticket::factory()->create(['property_id' => $this->property->id]);
});

function figures(): DashboardFigures
{
    return new DashboardFigures(User::current(), CarbonImmutable::now('Asia/Jakarta'));
}

it('adds up occupancy, income, arrears, tickets, and payments to check', function () {
    $figures = figures();

    expect($figures->occupancy())->toBe(['rooms' => 3, 'occupied' => 2, 'maintenance' => 0, 'percent' => 67])
        ->and($figures->revenueThisMonth())->toBe(2_400_000)
        ->and($figures->receivedThisMonth())->toBe(1_000_000)
        ->and($figures->arrears())->toBe(['amount' => 1_400_000, 'contracts' => 1])
        ->and($figures->openTickets())->toBe(['open' => 2, 'pressing' => 1])
        ->and($figures->pendingPayments())->toBe(['count' => 1, 'amount' => 500_000]);
});

it('shows the owner every figure on the dashboard', function () {
    Livewire::test(BusinessOverview::class)
        ->assertSee(['Okupansi', '67%', '2 dari 3 kamar terisi'])
        ->assertSee(['Pendapatan bulan ini', 'Rp2.400.000', 'Uang diterima: Rp1.000.000'])
        ->assertSee(['Tunggakan', 'Rp1.400.000', '1 kontrak lewat jatuh tempo'])
        ->assertSee(['Tiket terbuka', '1 prioritas tinggi atau darurat'])
        ->assertSee(['Menunggu verifikasi', 'Senilai Rp500.000']);

    $this->get('/app')->assertOk()->assertSee('Okupansi');
});

it('shows a caretaker only the figures of their role and properties', function () {
    $caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->property, $caretaker);
    loginAs($caretaker);

    Livewire::test(BusinessOverview::class)
        ->assertSee(['Okupansi', 'Tiket terbuka'])
        ->assertDontSee(['Pendapatan bulan ini', 'Menunggu verifikasi']);
});

it('lists overdue contracts with the payer to chase, leaving invoices not yet due out', function () {
    Livewire::test(Arrears::class)
        ->assertCanSeeTableRecords([$this->contractA])
        ->assertCanNotSeeTableRecords([$this->contractB])
        ->assertSee(['Total Rp1.400.000 dari 1 kontrak', '20 Sep 2026', '15 hari', 'Rp1.400.000'])
        ->assertSee($this->contractA->payer()->value('name'));
});

it('limits the arrears list to the properties a manager holds', function () {
    $manager = staff(Role::Manager, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle(Property::factory()->create(), $manager);
    loginAs($manager);

    Livewire::test(Arrears::class)
        ->assertCanNotSeeTableRecords([$this->contractA])
        ->assertSee('Tidak ada tagihan yang lewat jatuh tempo.');
});
