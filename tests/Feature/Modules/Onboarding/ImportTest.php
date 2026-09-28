<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Onboarding\Actions\ImportOnboardingData;
use App\Modules\Onboarding\Import\ImportResult;
use App\Modules\Onboarding\Import\ImportSheet;
use App\Modules\Onboarding\Import\ImportTemplate;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Modules\Property\States\Room\Occupied;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\BillingScenario;
use Tests\Support\ImportScenario;

beforeEach(function () {
    $this->travelTo('2026-10-05 03:00:00');
    $this->owner = loginAs(staff(Role::Owner));
    $this->property = Property::factory()->create();
    RoomType::factory()->create(['property_id' => $this->property->id, 'name' => 'Standar', 'default_capacity' => 2]);
});

const ROOM_HEADERS = ['Nomor kamar*', 'Tipe kamar*', 'Lantai', 'Kapasitas'];
const RESIDENT_HEADERS = ['Nama lengkap*', 'No. HP*', 'Jenis kelamin', 'Jenis identitas', 'No. identitas'];
const CONTRACT_HEADERS = ['Nomor kamar*', 'No. HP penghuni*', 'Periode sewa*', 'Harga sewa*', 'Deposit', 'Tanggal mulai*', 'Tagihan berikutnya mulai*', 'Nama pembayar', 'HP pembayar', 'Hubungan pembayar'];

function pilotWorkbook(array $extraRooms = [], array $extraContracts = []): string
{
    return ImportScenario::workbook([
        'Petunjuk' => [['Isi sheet berikut']],
        'Kamar' => [ROOM_HEADERS, ['101', 'standar', '1', ''], ['102', 'Standar', 1, 1], ...$extraRooms],
        'Penghuni' => [
            RESIDENT_HEADERS,
            ['Rina Wulandari', '0812 3456 7890', 'P', 'KTP', '3374015402030001'],
            ['Dewi Lestari', 81298765432, 'p', '', ''],
        ],
        'Kontrak' => [
            CONTRACT_HEADERS,
            ['101', '0812-3456-7890', 'Bulanan', 'Rp1.200.000', 1_200_000, '01/03/2026', new DateTimeImmutable('2026-10-01'), '', '', ''],
            ['102', '081298765432', 'bulanan', 1_100_000, '', '2026-04-15', '15/10/2026', 'Bapak Slamet', '0811 222 333', 'Orang tua'],
            ...$extraContracts,
        ],
    ]);
}

function runImport(string $path, bool $commit, string $fileName = 'data.xlsx', ?ImportSheet $csvSheet = null): ImportResult
{
    return app(ImportOnboardingData::class)->handle(test()->property, $path, $fileName, $commit, $csvSheet);
}

/**
 * @return list<string> "Sheet row: message"
 */
function importErrors(ImportResult $result): array
{
    $errors = [];

    foreach ($result->sheets() as $sheet) {
        foreach ($sheet['rows'] as $row) {
            foreach ($row['errors'] as $error) {
                $errors[] = "{$sheet['label']} {$row['number']}: {$error}";
            }
        }
    }

    return $errors;
}

it('previews every row without saving anything', function () {
    $result = runImport(pilotWorkbook(), commit: false);

    expect(importErrors($result))->toBe([])
        ->and($result->isClean())->toBeTrue()
        ->and($result->committed)->toBeFalse()
        ->and($result->rowCount(ImportSheet::Rooms))->toBe(2)
        ->and($result->rowCount(ImportSheet::Residents))->toBe(2)
        ->and($result->rowCount(ImportSheet::Contracts))->toBe(2)
        ->and($result->sheets()[2]['rows'][0]['summary'])->toBe('Kamar 101, 0812-3456-7890, Bulanan Rp1.200.000, mulai 1 Mar 2026, ditagih mulai 1 Okt 2026');

    expect(Room::query()->count())->toBe(0)
        ->and(Resident::query()->count())->toBe(0)
        ->and(Contract::query()->count())->toBe(0);
});

it('imports rooms, residents, and running contracts billed from the agreed period', function () {
    $result = runImport(pilotWorkbook(), commit: true);

    expect($result->committed)->toBeTrue()
        ->and(Room::query()->where('property_id', $this->property->id)->pluck('number')->sort()->values()->all())->toBe(['101', '102'])
        ->and(Room::query()->where('number', '101')->sole()->capacity)->toBe(2)
        ->and(Resident::query()->where('full_name', 'Dewi Lestari')->sole()->phone)->toBe('+6281298765432');

    $first = Contract::query()->whereHas('room', fn ($query) => $query->where('number', '101'))->sole();
    expect($first->status)->toBeInstanceOf(Active::class)
        ->and($first->isImported())->toBeTrue()
        ->and($first->rent_amount)->toBe(1_200_000)
        ->and($first->start_date->toDateString())->toBe('2026-03-01')
        ->and($first->next_period_start->toDateString())->toBe('2026-10-01')
        ->and($first->room()->firstOrFail()->status)->toBeInstanceOf(Occupied::class)
        ->and($first->primaryResident()?->full_name)->toBe('Rina Wulandari');

    $second = Contract::query()->whereHas('room', fn ($query) => $query->where('number', '102'))->sole();
    expect($second->payer()->firstOrFail()->name)->toBe('Bapak Slamet')
        ->and($second->deposit_amount)->toBe(0);

    [$invoice] = BillingScenario::issueDue($first);
    expect($invoice->period_start->toDateString())->toBe('2026-10-01')
        ->and(BillingScenario::lines($invoice))->toBe([['rent', 1_200_000]]);
});

it('lists the errors of every row and saves nothing when one row fails', function () {
    $result = runImport(pilotWorkbook(
        extraRooms: [['103', 'Deluxe', '', ''], ['101', 'Standar', '', '']],
        extraContracts: [['102', '0899 0000 1111', 'Bulanan', '1.000.000', '', '01/05/2026', '01/10/2026', '', '', ''], ['104', '0812 3456 7890', 'Mingguan', '500.000', '', '01/09/2026', '01/10/2026', '', '', '']],
    ), commit: true);

    expect($result->committed)->toBeFalse()
        ->and(importErrors($result))->toBe([
            'Kamar 4: Tipe kamar "Deluxe" belum ada di '.$this->property->name.'. Buat dulu di menu Tipe kamar.',
            'Kamar 5: Kamar 101 sudah ada.',
            'Kontrak 4: Penghuni dengan No. HP 0899 0000 1111 tidak ada di sheet Penghuni maupun data penghuni.',
            'Kontrak 5: Kamar 104 tidak ada di '.$this->property->name.' atau di sheet Kamar.',
        ]);

    expect(Room::query()->count())->toBe(0)
        ->and(Resident::query()->count())->toBe(0)
        ->and(Contract::query()->count())->toBe(0);
});

it('reports every unreadable value of a row at once', function () {
    $result = runImport(ImportScenario::workbook([
        'Kamar' => [ROOM_HEADERS, ['101', 'Standar', '', '']],
        'Kontrak' => [CONTRACT_HEADERS, ['101', '0812 3456 7890', 'Bulanan', 'seratus ribu', '', '31/02/2026', '01/10/2026', '', '', '']],
    ]), commit: false);

    expect(importErrors($result))->toBe([
        'Kontrak 2: Harga sewa harus berupa angka rupiah, misal 1200000.',
        'Kontrak 2: Tanggal mulai tidak bisa dibaca. Tulis seperti 31/12/2026.',
    ]);
});

it('refuses a sheet without its required columns', function () {
    $result = runImport(ImportScenario::workbook(['Kamar' => [['Nomor kamar'], ['101']]]), commit: false);

    expect($result->fileErrors())->toBe([
        'Sheet Kamar tidak punya kolom Tipe kamar. Pakai judul kolom dari template di baris pertama.',
    ])->and($result->isClean())->toBeFalse();
});

it('reads a semicolon CSV saved from Excel for the chosen sheet', function () {
    $result = runImport(ImportScenario::csv([
        ['Nama lengkap', 'No. HP'],
        ['Rina Wulandari', '0812 3456 7890'],
    ]), commit: true, fileName: 'penghuni.csv', csvSheet: ImportSheet::Residents);

    expect($result->committed)->toBeTrue()
        ->and(Resident::query()->sole()->full_name)->toBe('Rina Wulandari');
});

it('serves a template whose sheets the importer recognises', function () {
    $response = $this->get(route('onboarding.import-template'))->assertOk();

    $copy = sys_get_temp_dir().DIRECTORY_SEPARATOR.'template-copy-'.uniqid().'.xlsx';
    file_put_contents($copy, $response->streamedContent());

    expect($response->headers->get('content-disposition'))->toContain(ImportTemplate::FILENAME)
        ->and(runImport($copy, commit: false)->fileErrors())
        ->toBe(['Berkas belum berisi data. Isi sheet Kamar, Penghuni, atau Kontrak mulai baris kedua.']);
});

it('keeps the import to the owner', function () {
    $manager = loginAs(staff(Role::Manager, $this->owner->tenant()->firstOrFail()));

    expect(fn () => runImport(pilotWorkbook(), commit: false))->toThrow(AuthorizationException::class);

    $this->actingAs($manager)->get(route('onboarding.import-template'))->assertForbidden();
});
