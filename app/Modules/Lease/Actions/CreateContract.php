<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Drafts a contract (FR-KTR-01, FR-KTR-02). The rent and deposit are locked
 * here; the contract gets its number and occupies the room on activation.
 * The first resident listed is the primary one.
 */
final class CreateContract extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Contract
    {
        $data = $this->validate(self::normalized($input), self::rules($this->tenants->id()));

        $room = Room::query()->with('property')->whereKey($data['room_id'])->firstOrFail();
        $property = $room->property()->firstOrFail();

        $this->authorize('createIn', [Contract::class, $property]);

        /** @var list<string> $residentIds */
        $residentIds = array_values($data['resident_ids']);
        $residents = self::residents($residentIds);

        self::ensureFits($room, $residents);
        self::ensureNotLivingElsewhere($residents);
        self::ensureGenderPolicy($property->gender_policy, $residents);

        $period = RentalPeriod::from($data['rental_period']);
        $start = CarbonImmutable::parse($data['start_date']);
        $settings = $property->resolvedSettings();

        return $this->transaction(function () use ($data, $room, $residents, $period, $start, $settings): Contract {
            $contract = Contract::create([
                'property_id' => $room->property_id,
                'room_id' => $room->id,
                'payer_id' => $this->payer($data, $residents->first())->id,
                'rental_period' => $period,
                'rent_amount' => $data['rent_amount'],
                'deposit_amount' => $data['deposit_amount'],
                'start_date' => $start,
                'end_date' => $data['end_date'] ?? null,
                'billing_anchor_day' => $settings->billing_mode === BillingMode::FixedDate && $period->isMonthBased()
                    ? (int) $settings->fixed_billing_day
                    : $start->day,
                'next_period_start' => $start,
                'notify_resident' => $data['notify_resident'] ?? true,
                'notify_payer' => $data['notify_payer'] ?? true,
                'early_termination_penalty_amount' => $data['early_termination_penalty_amount'] ?? null,
                'clauses' => $data['clauses'] ?? null,
                'created_by' => $this->userId(),
            ]);

            foreach ($residents->values() as $index => $resident) {
                ContractResident::create([
                    'contract_id' => $contract->id,
                    'resident_id' => $resident->id,
                    'is_primary' => $index === 0,
                    'joined_on' => $start,
                ]);
            }

            return $contract;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $tenantId): array
    {
        $phone = fn (string $attribute, mixed $value, Closure $fail) => $value === null || Phone::isValid(is_string($value) ? $value : null)
            ? null
            : $fail('Nomor telepon tidak valid. Contoh: 0812 3456 7890.');

        return [
            'room_id' => ['required', Rule::exists('rooms', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'resident_ids' => ['required', 'array', 'min:1'],
            'resident_ids.*' => ['distinct', Rule::exists('residents', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'payer' => ['required', Rule::in(['self', 'other'])],
            'payer_name' => ['nullable', 'required_if:payer,other', 'string', 'max:150'],
            'payer_phone' => ['nullable', 'required_if:payer,other', 'string', $phone],
            'payer_email' => ['nullable', 'email', 'max:150'],
            'payer_relation' => ['nullable', 'required_if:payer,other', Rule::enum(PayerRelation::class)->except([PayerRelation::Self])],
            'rental_period' => ['required', Rule::enum(RentalPeriod::class)],
            'rent_amount' => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['required', 'integer', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'early_termination_penalty_amount' => ['nullable', 'integer', 'min:0'],
            'notify_resident' => ['boolean'],
            'notify_payer' => ['boolean'],
            'clauses' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalized(array $input): array
    {
        if (array_key_exists('payer_phone', $input)) {
            $input['payer_phone'] = Phone::normalize(is_string($input['payer_phone']) ? $input['payer_phone'] : null);
        }

        return $input;
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, Resident>
     */
    public static function residents(array $ids): Collection
    {
        $found = Resident::query()->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(fn (string $id): Resident => $found->get($id) ?? throw ValidationException::withMessages([
            'resident_ids' => 'Penghuni tidak ditemukan.',
        ]))->values();
    }

    /**
     * @param  Collection<int, Resident>  $residents
     */
    public static function ensureFits(Room $room, Collection $residents): void
    {
        if ($residents->count() > $room->capacity) {
            throw ValidationException::withMessages([
                'resident_ids' => "Kamar {$room->number} hanya untuk {$room->capacity} orang.",
            ]);
        }
    }

    /**
     * A resident lives in one room at a time.
     *
     * @param  Collection<int, Resident>  $residents
     */
    public static function ensureNotLivingElsewhere(Collection $residents, ?Contract $ignore = null): void
    {
        foreach ($residents as $resident) {
            $running = $resident->runningContract();

            if ($running !== null && $running->id !== $ignore?->id) {
                throw ValidationException::withMessages([
                    'resident_ids' => "{$resident->full_name} masih punya kontrak berjalan.",
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, Resident>  $residents
     */
    public static function ensureGenderPolicy(GenderPolicy $policy, Collection $residents): void
    {
        $allowed = match ($policy) {
            GenderPolicy::Male => Gender::Male,
            GenderPolicy::Female => Gender::Female,
            GenderPolicy::Mixed => null,
        };

        foreach ($residents as $resident) {
            if ($allowed !== null && $resident->gender !== null && $resident->gender !== $allowed) {
                throw ValidationException::withMessages([
                    'resident_ids' => "{$resident->full_name} tidak sesuai dengan jenis kost {$policy->getLabel()}.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payer(array $data, ?Resident $primary): Payer
    {
        if ($data['payer'] === 'other') {
            return Payer::create([
                'name' => $data['payer_name'],
                'phone' => $data['payer_phone'],
                'email' => $data['payer_email'] ?? null,
                'relation' => $data['payer_relation'],
            ]);
        }

        if ($primary === null) {
            throw ValidationException::withMessages(['resident_ids' => 'Pilih minimal satu penghuni.']);
        }

        return Payer::query()->firstOrCreate(
            ['resident_id' => $primary->id, 'relation' => PayerRelation::Self->value],
            ['name' => $primary->full_name, 'phone' => $primary->phone, 'email' => $primary->email],
        );
    }

    private function userId(): ?string
    {
        $actor = $this->actors->current();

        return $actor->type === ActorType::User ? $actor->id : null;
    }
}
