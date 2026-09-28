<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\Expense;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ListTickets;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ReportTicket;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ViewTicket;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Done;
use App\Modules\Property\Actions\AssignStaffToProperty;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\LeaseScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-09-20 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->room = LeaseScenario::room();
    $this->caretaker = staff(Role::Caretaker, $this->owner->tenant()->firstOrFail());
    app(AssignStaffToProperty::class)->handle($this->room->property()->firstOrFail(), $this->caretaker);
});

it('takes a ticket from report to confirmation through the pages', function () {
    loginAs($this->caretaker);

    Livewire::test(ReportTicket::class)
        ->fillForm([
            'property_id' => $this->room->property_id,
            'room_id' => $this->room->id,
            'title' => 'Lampu kamar mati',
            'category' => 'electrical',
            'priority' => 'normal',
            'description' => 'Sudah ganti bohlam, tetap mati.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ticket = Ticket::query()->sole();

    Livewire::test(ListTickets::class)->assertCanSeeTableRecords([$ticket]);
    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->assertActionHidden('assign')
        ->assertActionHidden('confirm');

    loginAs($this->owner);
    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('assign', ['assigned_user_id' => $this->caretaker->id])
        ->assertNotified('Tiket ditugaskan');

    loginAs($this->caretaker);
    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('start')
        ->assertNotified('Tiket sedang dikerjakan')
        ->callAction('resolve', ['note' => 'Saklar diganti', 'cost_amount' => '0'])
        ->assertNotified('Menunggu konfirmasi');

    loginAs($this->owner);
    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('confirm')
        ->assertNotified('Tiket selesai')
        ->assertSee(['Saklar diganti', 'Ditugaskan ke '.$this->caretaker->name]);

    expect($ticket->refresh()->status)->toBeInstanceOf(Done::class);
});

it('records the cost when resolving and books it on confirmation', function () {
    $ticket = app(App\Modules\Maintenance\Actions\ReportTicket::class)->handle($this->room->property()->firstOrFail(), [
        'category' => 'plumbing', 'priority' => 'high', 'title' => 'Pipa dapur bocor', 'description' => 'Air menggenang.',
    ]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->callAction('assign', ['assigned_user_id' => $this->owner->id])
        ->callAction('start')
        ->callAction('resolve', [
            'note' => 'Sambungan pipa diganti',
            'cost_amount' => '120000',
            'paid_from_account_id' => Account::system(AccountSubtype::Cash)->id,
        ])
        ->callAction('confirm')
        ->assertNotified('Tiket selesai')
        ->assertSee('Rp120.000');

    expect(Expense::query()->sole()->amount)->toBe(120_000);
});
