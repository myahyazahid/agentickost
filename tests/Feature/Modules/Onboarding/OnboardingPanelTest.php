<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Models\Contract;
use App\Modules\Onboarding\Filament\App\Pages\ImportData;
use App\Modules\Onboarding\Filament\App\Pages\SetUpProperty;
use App\Modules\Onboarding\Filament\App\Widgets\OnboardingChecklist;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\ImportScenario;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
});

function importAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent('importActions', 'form');
}

/**
 * Livewire deletes temporary uploads older than a day by the clock, so
 * upload tests run on the real date.
 */
function uploadedWorkbook(): UploadedFile
{
    test()->travelBack();

    $path = ImportScenario::workbook([
        'Kamar' => [['Nomor kamar*', 'Tipe kamar*'], ['101', 'Standar']],
        'Penghuni' => [['Nama lengkap*', 'No. HP*'], ['Rina Wulandari', '0812 3456 7890']],
        'Kontrak' => [
            ['Nomor kamar*', 'No. HP penghuni*', 'Periode sewa*', 'Harga sewa*', 'Tanggal mulai*', 'Tagihan berikutnya mulai*'],
            ['101', '0812 3456 7890', 'Bulanan', '1.200.000', '01/03/2026', '01/10/2026'],
        ],
    ]);

    $file = UploadedFile::fake()->create('data-kost.xlsx');
    file_put_contents($file->getRealPath(), (string) file_get_contents($path));

    return $file;
}

it('sets up a property through the wizard and moves on to the import', function () {
    Livewire::test(SetUpProperty::class)
        ->fillForm([
            'name' => 'Kost Melati',
            'code' => 'MLT',
            'gender_policy' => 'mixed',
            'timezone' => 'Asia/Jakarta',
            'address' => 'Jl. Melati 5',
            'city' => 'Semarang',
            'province' => 'Jawa Tengah',
            'billing_mode' => 'anniversary',
            'penalty_type' => 'flat',
            'penalty_amount' => '25.000',
            'room_types' => [
                ['name' => 'Standar', 'default_capacity' => 1, 'rental_period' => 'monthly', 'price' => '900.000', 'room_numbers' => ['101', '102']],
            ],
            'bank_accounts' => [
                ['kind' => 'bank', 'provider_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'Siti Aminah'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('Kost Melati siap dipakai')
        ->assertRedirect(ImportData::getUrl());

    $property = Property::query()->sole();
    expect($property->resolvedSettings()->penalty_amount)->toBe(25_000)
        ->and($property->rooms()->count())->toBe(2);
});

it('shows a wizard error on the step field that caused it', function () {
    Livewire::test(SetUpProperty::class)
        ->fillForm([
            'name' => 'Kost Melati', 'code' => 'MLT', 'gender_policy' => 'mixed', 'timezone' => 'Asia/Jakarta',
            'address' => 'Jl. Melati 5', 'city' => 'Semarang', 'province' => 'Jawa Tengah',
            'room_types' => [
                ['name' => 'Standar', 'default_capacity' => 1, 'rental_period' => 'monthly', 'price' => '900.000', 'room_numbers' => ['101']],
                ['name' => 'AC', 'default_capacity' => 1, 'rental_period' => 'monthly', 'price' => '1.300.000', 'room_numbers' => ['101']],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['room_types.1.room_numbers']);

    expect(Property::query()->count())->toBe(0);
});

it('checks an uploaded file before importing it', function () {
    $property = Property::factory()->create();
    RoomType::factory()->create(['property_id' => $property->id, 'name' => 'Standar']);

    Livewire::test(ImportData::class)
        ->fillForm(['property_id' => $property->id, 'file' => uploadedWorkbook()])
        ->assertActionDisabled(importAction('import'))
        ->callAction(importAction('check'))
        ->assertNotified('Semua baris benar')
        ->assertSee(['3 baris siap diimpor', 'Rina Wulandari, 0812 3456 7890', 'Siap diimpor'])
        ->assertActionEnabled(importAction('import'))
        ->callAction(importAction('import'))
        ->assertNotified('Data diimpor')
        ->assertSee('Diimpor: 3 baris');

    expect(Room::query()->where('property_id', $property->id)->count())->toBe(1)
        ->and(Contract::query()->sole()->isImported())->toBeTrue();
});

it('lists the rows to fix and keeps the import button off', function () {
    Property::factory()->create();

    Livewire::test(ImportData::class)
        ->fillForm(['file' => uploadedWorkbook()])
        ->set('data.property_id', Property::query()->value('id'))
        ->callAction(importAction('check'))
        ->assertNotified('Ada yang perlu diperbaiki')
        ->assertSee(['Tipe kamar "Standar" belum ada', 'Perlu diperbaiki'])
        ->assertActionDisabled(importAction('import'));

    expect(Room::query()->count())->toBe(0);
});

it('keeps the onboarding pages to the owner', function () {
    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    $this->get(SetUpProperty::getUrl())->assertForbidden();
    $this->get(ImportData::getUrl())->assertForbidden();
});

it('shows the checklist on the dashboard until every step is done', function () {
    $this->get(Dashboard::getUrl())->assertOk()->assertSee('Persiapan Agentic Kost');

    Livewire::test(OnboardingChecklist::class)
        ->assertSee(['0 dari 5 langkah selesai', 'Siapkan properti', 'Posting saldo awal']);

    loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));
    $this->get(Dashboard::getUrl())->assertOk()->assertDontSee('Persiapan Agentic Kost');
});
