<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Property\Database\Factories\PropertySettingFactory;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\PenaltyType;
use App\Modules\Property\Enums\ProrationBasis;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Billing and occupancy rules of one property (FR-PRP-04). Kept apart from
 * `properties` so rule changes show up clearly in the audit log.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property BillingMode $billing_mode
 * @property int|null $fixed_billing_day
 * @property int $invoice_lead_days
 * @property ProrationBasis $proration_basis
 * @property int $grace_days
 * @property PenaltyType $penalty_type
 * @property int|null $penalty_amount
 * @property string|null $penalty_percent
 * @property int|null $penalty_max_amount
 * @property list<string> $allocation_order
 * @property int $notice_days
 * @property int $booking_hold_hours
 * @property list<array<string, mixed>> $cancellation_policy
 * @property int $rounding_unit
 */
#[Fillable([
    'billing_mode', 'fixed_billing_day', 'invoice_lead_days', 'proration_basis', 'grace_days',
    'penalty_type', 'penalty_amount', 'penalty_percent', 'penalty_max_amount', 'allocation_order',
    'notice_days', 'booking_hold_hours', 'cancellation_policy', 'rounding_unit',
])]
#[UseFactory(PropertySettingFactory::class)]
class PropertySetting extends Model
{
    /** @use HasFactory<PropertySettingFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'billing_mode' => BillingMode::Anniversary,
            'fixed_billing_day' => null,
            'invoice_lead_days' => 7,
            'proration_basis' => ProrationBasis::ActualDays,
            'grace_days' => 3,
            'penalty_type' => PenaltyType::None,
            'penalty_amount' => null,
            'penalty_percent' => null,
            'penalty_max_amount' => null,
            'allocation_order' => AllocationCategory::defaultOrder(),
            'notice_days' => 30,
            'booking_hold_hours' => 24,
            'cancellation_policy' => [],
            'rounding_unit' => 1,
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return list<AllocationCategory>
     */
    public function allocationOrder(): array
    {
        return array_map(AllocationCategory::from(...), $this->allocation_order);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'billing_mode' => BillingMode::class,
            'fixed_billing_day' => 'integer',
            'invoice_lead_days' => 'integer',
            'proration_basis' => ProrationBasis::class,
            'grace_days' => 'integer',
            'penalty_type' => PenaltyType::class,
            'penalty_amount' => RupiahCast::class,
            'penalty_percent' => 'decimal:2',
            'penalty_max_amount' => RupiahCast::class,
            'allocation_order' => 'array',
            'notice_days' => 'integer',
            'booking_hold_hours' => 'integer',
            'cancellation_policy' => 'array',
            'rounding_unit' => 'integer',
        ];
    }
}
