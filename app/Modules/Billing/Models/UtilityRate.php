<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\UtilityRateFactory;
use App\Modules\Billing\Enums\MeterUnit;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How a property charges one utility (FR-UTL-01): per metered unit, as a
 * flat monthly fee, or not at all when residents buy prepaid tokens.
 * History is kept like room prices: rows are closed, not edited.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property UtilityKind $utility
 * @property UtilityMode $mode
 * @property MeterUnit|null $unit
 * @property int $rate_amount
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 */
#[Fillable(['property_id', 'utility', 'mode', 'unit', 'rate_amount', 'effective_from', 'effective_until'])]
#[UseFactory(UtilityRateFactory::class)]
class UtilityRate extends Model
{
    /** @use HasFactory<UtilityRateFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, HasFactory, HasUlids;

    /**
     * The rate in force on a date for one utility of a property.
     */
    public static function inForce(string $propertyId, UtilityKind $utility, CarbonInterface $date): ?self
    {
        return self::query()
            ->where('property_id', $propertyId)
            ->where('utility', $utility->value)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $range) => $range->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))
            ->latest('effective_from')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'utility' => UtilityKind::class,
            'mode' => UtilityMode::class,
            'unit' => MeterUnit::class,
            'rate_amount' => RupiahCast::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }
}
