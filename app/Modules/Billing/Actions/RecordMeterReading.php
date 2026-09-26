<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Engine\Proration;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Property\Models\Room;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records a room's meter reading with a photo of the meter (FR-UTL-02).
 *
 * The first reading of a room only sets the starting point. After that the
 * new number may not be lower than the last one unless the meter was
 * replaced, in which case the usage counts from the new meter's starting
 * number (FR-UTL-03). Sending the same client_uuid twice records it once.
 */
final class RecordMeterReading extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Room $room, array $input): MeterReading
    {
        $this->authorize('recordFor', [MeterReading::class, $room]);

        $data = $this->validate($input, [
            'utility' => ['required', Rule::enum(UtilityKind::class)],
            'reading_date' => ['required', 'date'],
            'current_value' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'is_meter_replaced' => ['sometimes', 'boolean'],
            'new_meter_start_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['string'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        if (($data['client_uuid'] ?? null) !== null) {
            $existing = MeterReading::query()->where('client_uuid', $data['client_uuid'])->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $property = $room->property()->firstOrFail();
        $utility = UtilityKind::from($data['utility']);
        $date = CarbonImmutable::parse($data['reading_date'])->startOfDay();

        if ($date->greaterThan($property->today())) {
            throw ValidationException::withMessages(['reading_date' => 'Tanggal pencatatan tidak boleh di masa depan.']);
        }

        $rate = UtilityRate::inForce($property->id, $utility, $date);

        if ($rate === null || $rate->mode !== UtilityMode::Metered) {
            throw ValidationException::withMessages([
                'utility' => "{$utility->getLabel()} di properti ini tidak memakai meteran per kamar pada tanggal itu.",
            ]);
        }

        return $this->transaction(function () use ($room, $utility, $date, $rate, $data): MeterReading {
            $history = MeterReading::query()
                ->where('room_id', $room->id)
                ->where('utility', $utility->value)
                ->lockForUpdate();

            if ((clone $history)->whereDate('reading_date', '>=', $date)->exists()) {
                throw ValidationException::withMessages([
                    'reading_date' => 'Sudah ada pencatatan pada tanggal ini atau sesudahnya untuk kamar ini.',
                ]);
            }

            $last = (clone $history)->latest('reading_date')->first();
            $current = self::normalize($data['current_value']);
            $replaced = (bool) ($data['is_meter_replaced'] ?? false);

            $previous = match (true) {
                $replaced => self::normalize($data['new_meter_start_value'] ?? '0'),
                $last !== null => self::normalize($last->current_value),
                default => $current,
            };

            if (bccomp($current, $previous, 2) < 0) {
                throw ValidationException::withMessages([
                    'current_value' => $replaced
                        ? 'Angka meteran tidak boleh lebih kecil dari angka awal meteran baru.'
                        : 'Angka meteran tidak boleh lebih kecil dari catatan sebelumnya ('.self::display($previous).'). Tandai ganti meteran bila meterannya baru.',
                ]);
            }

            $usageHundredths = (int) bcmul(bcsub($current, $previous, 2), '100', 0);
            $actor = $this->actors->current();

            $reading = MeterReading::create([
                'room_id' => $room->id,
                'utility' => $utility,
                'reading_date' => $date,
                'previous_value' => $previous,
                'current_value' => $current,
                'is_meter_replaced' => $replaced,
                'rate_amount' => $rate->rate_amount,
                'amount' => Proration::divideRounded($usageHundredths * $rate->rate_amount, 100),
                'recorded_by' => $actor->type === ActorType::User ? $actor->id : null,
                'client_uuid' => $data['client_uuid'] ?? null,
            ]);

            $this->attachments->sync($reading, AttachmentCollection::Meter, array_values($data['photos']), 'photos');

            return $reading;
        });
    }

    /**
     * @return numeric-string
     */
    private static function normalize(mixed $value): string
    {
        return bcadd(is_numeric($value) ? (string) $value : '0', '0', 2);
    }

    private static function display(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }
}
