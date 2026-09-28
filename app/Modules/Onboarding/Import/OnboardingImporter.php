<?php

namespace App\Modules\Onboarding\Import;

use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Actions\RegisterRunningContract;
use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\IdentityType;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Actions\CreateRoom;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomType;
use App\Support\Money\Rupiah;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Writes the rows of an import file through the Actions that own the data:
 * rooms, then residents, then running contracts, so a contract can refer to
 * a room or resident from the same file. Each row runs in its own savepoint:
 * a failing row is rolled back and reported, and the others still run, so
 * the preview lists every problem at once (FR-ONB-03). Call inside a
 * transaction; the caller decides whether to keep the result.
 */
final class OnboardingImporter
{
    /**
     * Residents created by this import, by normalized phone number.
     *
     * @var array<string, string>
     */
    private array $importedResidents = [];

    public function __construct(
        private readonly CreateRoom $createRoom,
        private readonly CreateResident $createResident,
        private readonly RegisterRunningContract $registerContract,
    ) {}

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $sheets  rows by sheet value, keyed by row number
     */
    public function run(Property $property, array $sheets, ImportResult $result): ImportResult
    {
        $this->importedResidents = [];

        foreach ($sheets[ImportSheet::Rooms->value] ?? [] as $number => $row) {
            $this->row($result, ImportSheet::Rooms, $number, self::roomSummary($row), fn () => $this->room($property, $row));
        }

        $phonesSeen = [];

        foreach ($sheets[ImportSheet::Residents->value] ?? [] as $number => $row) {
            $this->row($result, ImportSheet::Residents, $number, self::residentSummary($row), function () use ($row, $number, &$phonesSeen): void {
                $this->resident($row, $number, $phonesSeen);
            });
        }

        foreach ($sheets[ImportSheet::Contracts->value] ?? [] as $number => $row) {
            $this->row($result, ImportSheet::Contracts, $number, self::contractSummary($row), fn () => $this->contract($property, $row));
        }

        return $result;
    }

    /**
     * @param  Closure(): void  $write
     */
    private function row(ImportResult $result, ImportSheet $sheet, int $number, string $summary, Closure $write): void
    {
        try {
            DB::transaction($write);
            $result->addRow($sheet, $number, $summary);
        } catch (ValidationException $exception) {
            $result->addRow($sheet, $number, $summary, array_values(array_unique(Arr::flatten($exception->errors()))));
        } catch (InvalidArgumentException $exception) {
            $result->addRow($sheet, $number, $summary, [$exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function room(Property $property, array $row): void
    {
        $number = self::required($row, 'nomor_kamar', 'Nomor kamar');
        $typeName = self::required($row, 'tipe_kamar', 'Tipe kamar');

        $roomType = RoomType::query()
            ->where('property_id', $property->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($typeName)])
            ->first() ?? self::fail("Tipe kamar \"{$typeName}\" belum ada di {$property->name}. Buat dulu di menu Tipe kamar.");

        if (Room::query()->where('property_id', $property->id)->where('number', $number)->exists()) {
            self::fail("Kamar {$number} sudah ada.");
        }

        $this->createRoom->handle($property, [
            'room_type_id' => $roomType->id,
            'number' => $number,
            'floor' => Cell::text($row['lantai'] ?? null),
            'capacity' => Cell::integer($row['kapasitas'] ?? null, 'Kapasitas'),
            'notes' => Cell::text($row['catatan'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $phonesSeen  row number by phone, within this file
     */
    private function resident(array $row, int $number, array &$phonesSeen): void
    {
        $name = self::required($row, 'nama_lengkap', 'Nama lengkap');
        $phone = Phone::normalize(self::required($row, 'no_hp', 'No. HP'));

        if ($phone === null || ! Phone::isValid($phone)) {
            self::fail('No. HP tidak valid. Contoh: 0812 3456 7890.');
        }

        if (isset($phonesSeen[$phone])) {
            self::fail("No. HP sama dengan baris {$phonesSeen[$phone]}. Setiap penghuni perlu nomor sendiri.");
        }

        $phonesSeen[$phone] = $number;

        $existing = Resident::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            self::fail("No. HP ini sudah terdaftar atas nama {$existing->full_name}. Hapus baris ini; sheet Kontrak tetap bisa memakai nomornya.");
        }

        $email = Cell::text($row['email'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail('Email tidak valid.');
        }

        $identityNumber = Cell::text($row['no_identitas'] ?? null);
        $identityType = Cell::choice($row['jenis_identitas'] ?? null, IdentityType::class, 'Jenis identitas', ['Kartu pelajar' => IdentityType::StudentCard, 'Kartu mahasiswa' => IdentityType::StudentCard]);

        if ($identityNumber !== null && $identityType === null) {
            self::fail('Isi jenis identitas untuk nomor identitas ini.');
        }

        $birthDate = Cell::date($row['tanggal_lahir'] ?? null, 'Tanggal lahir');

        if ($birthDate !== null && $birthDate >= CarbonImmutable::today()->toDateString()) {
            self::fail('Tanggal lahir harus sebelum hari ini.');
        }

        $emergencyPhone = Phone::normalize(Cell::text($row['hp_kontak_darurat'] ?? null));

        if ($emergencyPhone !== null && ! Phone::isValid($emergencyPhone)) {
            self::fail('HP kontak darurat tidak valid.');
        }

        $resident = $this->createResident->handle([
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'gender' => Cell::choice($row['jenis_kelamin'] ?? null, Gender::class, 'Jenis kelamin', ['L' => Gender::Male, 'P' => Gender::Female, 'Pria' => Gender::Male, 'Wanita' => Gender::Female]),
            'birth_date' => $birthDate,
            'identity_type' => $identityType,
            'identity_number' => $identityNumber,
            'institution' => Cell::text($row['institusi'] ?? null),
            'emergency_contact_name' => Cell::text($row['kontak_darurat'] ?? null),
            'emergency_contact_phone' => $emergencyPhone,
            'emergency_contact_relation' => Cell::text($row['hubungan_kontak_darurat'] ?? null),
            'vehicle_plate' => Cell::text($row['plat_kendaraan'] ?? null),
        ]);

        $this->importedResidents[$phone] = $resident->id;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function contract(Property $property, array $row): void
    {
        $problems = [];
        $read = function (Closure $parse) use (&$problems): mixed {
            try {
                return $parse();
            } catch (InvalidArgumentException $exception) {
                $problems[] = $exception->getMessage();

                return null;
            }
        };

        $roomNumber = $read(fn (): string => self::required($row, 'nomor_kamar', 'Nomor kamar'));
        $phones = $read(fn (): array => self::phones(self::required($row, 'no_hp_penghuni', 'No. HP penghuni')));
        $period = $read(fn (): ?RentalPeriod => Cell::choice(self::required($row, 'periode_sewa', 'Periode sewa'), RentalPeriod::class, 'Periode sewa', ['Triwulan' => RentalPeriod::Quarterly, 'Semester' => RentalPeriod::Semiannual]));
        $rent = $read(fn (): ?int => Cell::amount(self::required($row, 'harga_sewa', 'Harga sewa', raw: true), 'Harga sewa'));
        $deposit = $read(fn (): ?int => Cell::amount($row['deposit'] ?? null, 'Deposit'));
        $startDate = $read(fn (): ?string => Cell::date(self::required($row, 'tanggal_mulai', 'Tanggal mulai', raw: true), 'Tanggal mulai'));
        $endDate = $read(fn (): ?string => Cell::date($row['tanggal_selesai'] ?? null, 'Tanggal selesai'));
        $billingStartsOn = $read(fn (): ?string => Cell::date(self::required($row, 'tagihan_berikutnya', 'Tagihan berikutnya mulai', raw: true), 'Tagihan berikutnya mulai'));
        $payerRelation = $read(fn (): ?PayerRelation => Cell::choice($row['hubungan_pembayar'] ?? null, PayerRelation::class, 'Hubungan pembayar', ['Wali' => PayerRelation::Guardian]));

        if ($problems !== []) {
            throw ValidationException::withMessages(['row' => $problems]);
        }

        if ($endDate !== null && $startDate !== null && $endDate < $startDate) {
            self::fail('Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $payerName = Cell::text($row['nama_pembayar'] ?? null);
        $payerPhone = Phone::normalize(Cell::text($row['hp_pembayar'] ?? null));

        if ($payerName !== null && ! Phone::isValid($payerPhone)) {
            self::fail('Isi HP pembayar dengan nomor yang valid.');
        }

        $room = Room::query()->where('property_id', $property->id)->where('number', $roomNumber)->first()
            ?? self::fail("Kamar {$roomNumber} tidak ada di {$property->name} atau di sheet Kamar.");

        $this->registerContract->handle([
            'room_id' => $room->id,
            'resident_ids' => array_map(fn (string $phone): string => $this->residentId($phone), $phones),
            'payer' => $payerName === null ? 'self' : 'other',
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'payer_relation' => $payerName === null ? null : ($payerRelation ?? PayerRelation::Other),
            'rental_period' => $period,
            'rent_amount' => $rent,
            'deposit_amount' => $deposit ?? 0,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_starts_on' => $billingStartsOn,
        ]);
    }

    private function residentId(string $phone): string
    {
        $normalized = (string) Phone::normalize($phone);

        if (isset($this->importedResidents[$normalized])) {
            return $this->importedResidents[$normalized];
        }

        $matches = Resident::query()->where('phone', $normalized)->limit(2)->pluck('id');

        return match ($matches->count()) {
            1 => (string) $matches->first(),
            0 => self::fail("Penghuni dengan No. HP {$phone} tidak ada di sheet Penghuni maupun data penghuni."),
            default => self::fail("Ada lebih dari satu penghuni dengan No. HP {$phone}."),
        };
    }

    /**
     * @return list<string>
     */
    private static function phones(string $cell): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/', $cell) ?: [])));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return ($raw is true ? mixed : string)
     */
    private static function required(array $row, string $key, string $column, bool $raw = false): mixed
    {
        $value = $row[$key] ?? null;

        if (Cell::text($value) === null) {
            self::fail("{$column} wajib diisi.");
        }

        return $raw ? $value : (string) Cell::text($value);
    }

    private static function fail(string $message): never
    {
        throw new InvalidArgumentException($message);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function roomSummary(array $row): string
    {
        return trim('Kamar '.Cell::text($row['nomor_kamar'] ?? null).', '.Cell::text($row['tipe_kamar'] ?? null), ', ');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function residentSummary(array $row): string
    {
        return trim(Cell::text($row['nama_lengkap'] ?? null).', '.Cell::text($row['no_hp'] ?? null), ', ');
    }

    /**
     * Shows dates as read, so a day and month swapped by Excel is caught in
     * the preview.
     *
     * @param  array<string, mixed>  $row
     */
    private static function contractSummary(array $row): string
    {
        $parts = ['Kamar '.Cell::text($row['nomor_kamar'] ?? null), Cell::text($row['no_hp_penghuni'] ?? null)];

        try {
            $rent = Cell::amount($row['harga_sewa'] ?? null, 'Harga sewa');
            $parts[] = trim(Cell::text($row['periode_sewa'] ?? null).' '.($rent !== null ? Rupiah::format($rent) : ''));

            $start = Cell::date($row['tanggal_mulai'] ?? null, 'Tanggal mulai');
            $billing = Cell::date($row['tagihan_berikutnya'] ?? null, 'Tagihan berikutnya mulai');
            $parts[] = $start !== null ? 'mulai '.CarbonImmutable::parse($start)->translatedFormat('j M Y') : null;
            $parts[] = $billing !== null ? 'ditagih mulai '.CarbonImmutable::parse($billing)->translatedFormat('j M Y') : null;
        } catch (InvalidArgumentException) {
            // The row itself reports the unreadable value.
        }

        return implode(', ', array_filter($parts));
    }
}
