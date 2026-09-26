<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\MeterUnit;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Billing\Models\UtilityRate;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sets how a property charges one utility from a date onward (FR-UTL-01).
 * The rate in force before that date is closed the day before, like room
 * prices. Readings already taken keep the rate they were recorded with.
 */
final class SetUtilityRate extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): UtilityRate
    {
        $this->authorize('createIn', [UtilityRate::class, $property]);

        $data = $this->validate($input, [
            'utility' => ['required', Rule::enum(UtilityKind::class)],
            'mode' => ['required', Rule::enum(UtilityMode::class)],
            'unit' => ['nullable', 'required_if:mode,'.UtilityMode::Metered->value, Rule::enum(MeterUnit::class)],
            'rate_amount' => ['nullable', 'required_unless:mode,'.UtilityMode::Token->value, 'integer', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        $utility = UtilityKind::from($data['utility']);
        $mode = UtilityMode::from($data['mode']);
        $from = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return $this->transaction(function () use ($property, $utility, $mode, $from, $data): UtilityRate {
            $later = UtilityRate::query()
                ->where('property_id', $property->id)
                ->where('utility', $utility->value)
                ->whereDate('effective_from', '>=', $from)
                ->lockForUpdate()
                ->exists();

            if ($later) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Sudah ada tarif yang berlaku mulai tanggal ini atau sesudahnya. Pilih tanggal yang lebih akhir.',
                ]);
            }

            UtilityRate::inForce($property->id, $utility, $from)?->update(['effective_until' => $from->subDay()]);

            return UtilityRate::create([
                'property_id' => $property->id,
                'utility' => $utility,
                'mode' => $mode,
                'unit' => $mode === UtilityMode::Metered ? $data['unit'] : null,
                'rate_amount' => $mode === UtilityMode::Token ? 0 : (int) $data['rate_amount'],
                'effective_from' => $from,
            ]);
        });
    }
}
