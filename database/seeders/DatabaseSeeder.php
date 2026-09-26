<?php

namespace Database\Seeders;

use App\Modules\Access\Actions\CreateUser;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\AccrueInvoicePenalties;
use App\Modules\Billing\Actions\CreateAdhocInvoice;
use App\Modules\Billing\Actions\IssueDueInvoices;
use App\Modules\Billing\Actions\RecordMeterReading;
use App\Modules\Billing\Actions\SetUtilityRate;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Finance\Actions\CreateBankAccount;
use App\Modules\Lease\Actions\ActivateContract;
use App\Modules\Lease\Actions\CreateContract;
use App\Modules\Lease\Actions\CreateResident;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Actions\AssignStaffToProperty;
use App\Modules\Property\Actions\CreateProperty;
use App\Modules\Property\Actions\CreateRoom;
use App\Modules\Property\Actions\CreateRoomType;
use App\Modules\Property\Actions\SetRoomPrice;
use App\Modules\Property\Actions\StartRoomMaintenance;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\Models\PlatformAdmin;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actors\Actor;
use App\Support\Actors\ActorContext;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Local demo data. Every login uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(ActorContext $actors, TenantContext $tenants): void
    {
        PlatformAdmin::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
        ]);

        $actors->actingAs(Actor::system(), function () use ($tenants): void {
            $tenant = app(CreateTenant::class)->handle(['name' => 'Kost Demo', 'slug' => 'kost-demo']);

            $tenants->run($tenant, fn () => $this->seedTenant());
        });
    }

    private function seedTenant(): void
    {
        $this->staff('Owner Demo', 'owner@example.com', Role::Owner);
        $manager = $this->staff('Manajer Demo', 'manajer@example.com', Role::Manager);
        $caretaker = $this->staff('Penjaga Demo', 'penjaga@example.com', Role::Caretaker);
        $this->staff('Akuntan Demo', 'akuntan@example.com', Role::Accountant);

        $property = app(CreateProperty::class)->handle([
            'name' => 'Kost Demo Kemang',
            'code' => 'KMG',
            'address' => 'Jl. Kemang Raya No. 1',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
            'timezone' => Timezone::Wib->value,
            'gender_policy' => GenderPolicy::Mixed->value,
            'facilities' => ['Wi-Fi', 'Parkir motor', 'Dapur bersama'],
        ]);

        app(AssignStaffToProperty::class)->handle($property, $manager);
        app(AssignStaffToProperty::class)->handle($property, $caretaker);

        $standard = app(CreateRoomType::class)->handle($property, ['name' => 'Standar', 'default_capacity' => 1, 'facilities' => ['Kasur', 'Lemari']]);
        $ac = app(CreateRoomType::class)->handle($property, ['name' => 'AC kamar mandi dalam', 'default_capacity' => 1, 'facilities' => ['AC', 'Kamar mandi dalam']]);

        $from = now()->startOfYear()->toDateString();
        app(SetRoomPrice::class)->handle($standard, ['rental_period' => 'monthly', 'amount' => 1_200_000, 'effective_from' => $from]);
        app(SetRoomPrice::class)->handle($ac, ['rental_period' => 'monthly', 'amount' => 1_750_000, 'effective_from' => $from]);
        app(SetRoomPrice::class)->handle($ac, ['rental_period' => 'daily', 'amount' => 120_000, 'effective_from' => $from]);

        $rooms = [];

        foreach (['101', '102', '103', '104'] as $number) {
            $rooms[$number] = app(CreateRoom::class)->handle($property, ['room_type_id' => $standard->id, 'number' => $number, 'floor' => '1']);
        }

        foreach (['201', '202'] as $number) {
            $rooms[$number] = app(CreateRoom::class)->handle($property, ['room_type_id' => $ac->id, 'number' => $number, 'floor' => '2']);
        }

        $underRepair = app(CreateRoom::class)->handle($property, ['room_type_id' => $ac->id, 'number' => '203', 'floor' => '2']);
        app(StartRoomMaintenance::class)->handle($underRepair);

        app(CreateBankAccount::class)->handle([
            'kind' => 'bank',
            'provider_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Owner Demo',
            'is_default' => true,
        ]);

        $this->seedContracts($rooms);
        $this->seedBilling($property, $rooms);
    }

    /**
     * @param  array<int, Room>  $rooms
     */
    private function seedContracts(array $rooms): void
    {
        $rina = app(CreateResident::class)->handle(['full_name' => 'Rina Kartika', 'phone' => '081234567801', 'gender' => 'female', 'institution' => 'Universitas Indonesia']);
        $andi = app(CreateResident::class)->handle(['full_name' => 'Andi Pratama', 'phone' => '081234567802', 'gender' => 'male', 'institution' => 'PT Maju Jaya']);
        $sari = app(CreateResident::class)->handle(['full_name' => 'Sari Dewi', 'phone' => '081234567803', 'gender' => 'female']);
        app(CreateResident::class)->handle(['full_name' => 'Yoga Saputra', 'phone' => '081234567804', 'gender' => 'male']);

        app(ActivateContract::class)->handle(app(CreateContract::class)->handle([
            'room_id' => $rooms[101]->id,
            'resident_ids' => [$rina->id],
            'payer' => 'self',
            'rental_period' => 'monthly',
            'rent_amount' => 1_200_000,
            'deposit_amount' => 1_200_000,
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
        ]));

        app(ActivateContract::class)->handle(app(CreateContract::class)->handle([
            'room_id' => $rooms[102]->id,
            'resident_ids' => [$andi->id],
            'payer' => 'other',
            'payer_name' => 'Budi Pratama',
            'payer_phone' => '081298765432',
            'payer_relation' => 'parent',
            'rental_period' => 'monthly',
            'rent_amount' => 1_200_000,
            'deposit_amount' => 1_000_000,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonths(3)->addYear()->subDay()->toDateString(),
            'early_termination_penalty_amount' => 600_000,
        ]));

        app(CreateContract::class)->handle([
            'room_id' => $rooms[201]->id,
            'resident_ids' => [$sari->id],
            'payer' => 'self',
            'rental_period' => 'monthly',
            'rent_amount' => 1_750_000,
            'deposit_amount' => 1_750_000,
            'start_date' => now()->addWeek()->toDateString(),
        ]);
    }

    /**
     * Utility rates, meter readings, and the invoices the scheduled job would
     * have issued so far, replayed day by day so each gets its real date.
     *
     * @param  array<int, Room>  $rooms
     */
    private function seedBilling(Property $property, array $rooms): void
    {
        app(UpdatePropertySettings::class)->handle($property, [
            ...$property->resolvedSettings()->attributesToArray(),
            'penalty_type' => PenaltyType::Flat->value,
            'penalty_amount' => 25_000,
        ]);

        $from = now()->startOfYear()->toDateString();
        app(SetUtilityRate::class)->handle($property, ['utility' => 'electricity', 'mode' => 'metered', 'unit' => 'kwh', 'rate_amount' => 1_500, 'effective_from' => $from]);
        app(SetUtilityRate::class)->handle($property, ['utility' => 'water', 'mode' => 'flat', 'rate_amount' => 50_000, 'effective_from' => $from]);
        app(SetUtilityRate::class)->handle($property, ['utility' => 'internet', 'mode' => 'token', 'effective_from' => $from]);

        $contracts = Contract::query()->whereIn('status', ContractState::runningValues())->with('room')->get();
        $readings = $this->readingSchedule(array_values($contracts->all()));
        $today = CarbonImmutable::now();
        $recordReadings = $this->canStoreFiles();

        if (! $recordReadings) {
            $this->command->warn('Contoh catatan meteran dilewati: penyimpanan file belum bisa ditulis (lihat README, bagian object storage).');
        }

        for ($day = $contracts->min('start_date')->toImmutable()->subWeek(); $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            CarbonImmutable::setTestNow($day->setTime(1, 0));
            Carbon::setTestNow($day->setTime(1, 0));

            foreach ($recordReadings ? ($readings[$day->toDateString()] ?? []) : [] as [$room, $value]) {
                app(RecordMeterReading::class)->handle($room, [
                    'utility' => 'electricity',
                    'reading_date' => $day->toDateString(),
                    'current_value' => $value,
                    'photos' => [$this->meterPhoto()],
                ]);
            }

            foreach ($contracts as $contract) {
                app(IssueDueInvoices::class)->handle($contract->refresh());
            }

            Invoice::query()->whereIn('status', InvoiceState::openValues())->get()
                ->each(fn (Invoice $invoice) => app(AccrueInvoicePenalties::class)->handle($invoice));
        }

        CarbonImmutable::setTestNow();
        Carbon::setTestNow();

        app(CreateAdhocInvoice::class)->handle($property, [
            'contract_id' => $contracts->firstWhere('room_id', $rooms[102]->id)?->id,
            'due_date' => $today->addWeek()->toDateString(),
            'items' => [['type' => 'damage', 'description' => 'Ganti kunci lemari', 'amount' => 150_000]],
        ]);
    }

    /**
     * A reading on move-in day, then one about every month.
     *
     * @param  list<Contract>  $contracts
     * @return array<string, list<array{0: Room, 1: int}>>
     */
    private function readingSchedule(array $contracts): array
    {
        $schedule = [];

        foreach ($contracts as $index => $contract) {
            /** @var Room $room */
            $room = $contract->room;
            $value = 1_500 + $index * 1_700;
            $date = $contract->start_date->toImmutable();

            while ($date->lessThan(CarbonImmutable::now())) {
                $schedule[$date->toDateString()][] = [$room, $value];
                $date = $date->addDays(30);
                $value += 80 + $index * 15;
            }
        }

        return $schedule;
    }

    private function canStoreFiles(): bool
    {
        try {
            Storage::put(app(TenantStorage::class)->path('.write-check'), 'ok');
            Storage::delete(app(TenantStorage::class)->path('.write-check'));

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function meterPhoto(): string
    {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 41, 59));
        ob_start();
        imagejpeg($image);
        $path = app(TenantStorage::class)->path(AttachmentCollection::Meter->directory().'/'.Str::ulid().'.jpg');
        Storage::put($path, (string) ob_get_clean());

        return $path;
    }

    private function staff(string $name, string $email, Role $role): User
    {
        return app(CreateUser::class)->handle([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role->value,
        ]);
    }
}
