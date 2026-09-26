<?php

namespace App\Modules\Property\Actions;

use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertySetting;
use App\Support\Actions\Action;
use Closure;
use Illuminate\Validation\Rule;

/**
 * Billing and occupancy rules of a property (FR-PRP-04). Values that do not
 * apply to the chosen mode are cleared, so a stale penalty amount never
 * lingers behind a "no penalty" setting.
 */
final class UpdatePropertySettings extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): PropertySetting
    {
        $this->authorize('manageSettings', $property);

        $data = $this->validate($input, [
            'billing_mode' => ['required', Rule::enum(BillingMode::class)],
            'fixed_billing_day' => ['nullable', 'required_if:billing_mode,'.BillingMode::FixedDate->value, 'integer', 'between:1,28'],
            'invoice_lead_days' => ['required', 'integer', 'between:0,60'],
            'proration_basis' => ['required', Rule::enum(ProrationBasis::class)],
            'grace_days' => ['required', 'integer', 'between:0,60'],
            'penalty_type' => ['required', Rule::enum(PenaltyType::class)],
            'penalty_amount' => [
                'nullable', 'integer', 'min:1',
                'required_if:penalty_type,'.PenaltyType::Flat->value.','.PenaltyType::Daily->value,
            ],
            'penalty_percent' => ['nullable', 'numeric', 'between:0.01,100', 'required_if:penalty_type,'.PenaltyType::Percent->value],
            'penalty_max_amount' => ['nullable', 'integer', 'min:1'],
            'allocation_order' => ['required', 'array', self::isFullOrder(...)],
            'notice_days' => ['required', 'integer', 'between:0,365'],
            'rounding_unit' => ['required', 'integer', Rule::in([1, 100, 500, 1000])],
        ]);

        $billingMode = BillingMode::from($data['billing_mode']);
        $penaltyType = PenaltyType::from($data['penalty_type']);

        $data['fixed_billing_day'] = $billingMode === BillingMode::FixedDate ? $data['fixed_billing_day'] : null;
        $data['penalty_amount'] = in_array($penaltyType, [PenaltyType::Flat, PenaltyType::Daily], true) ? $data['penalty_amount'] : null;
        $data['penalty_percent'] = $penaltyType === PenaltyType::Percent ? $data['penalty_percent'] : null;
        $data['penalty_max_amount'] = $penaltyType === PenaltyType::None ? null : ($data['penalty_max_amount'] ?? null);
        $data['allocation_order'] = array_values($data['allocation_order']);

        return $this->transaction(function () use ($property, $data): PropertySetting {
            $settings = $property->resolvedSettings();
            $settings->update($data);

            return $settings;
        });
    }

    /**
     * Every allocation category exactly once.
     */
    private static function isFullOrder(string $attribute, mixed $value, Closure $fail): void
    {
        $expected = AllocationCategory::defaultOrder();
        $given = is_array($value) ? array_values($value) : [];

        sort($expected);
        sort($given);

        if ($given !== $expected) {
            $fail('Urutan alokasi harus memuat setiap komponen tepat satu kali.');
        }
    }
}
