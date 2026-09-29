<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Subscription\Database\Factories\PlanFactory;
use App\Modules\Subscription\Enums\BillingCycle;
use App\Modules\Subscription\Enums\PlanFeature;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan with its limits and features (FR-SUB-01, FR-SUB-02).
 * Platform table, managed by super admins. A limit of null means no limit.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $monthly_price_amount
 * @property int|null $yearly_price_amount
 * @property int|null $max_rooms
 * @property int|null $max_properties
 * @property int|null $max_staff
 * @property int|null $monthly_message_quota
 * @property int|null $monthly_ai_credit_quota
 * @property list<string> $features
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable([
    'code', 'name', 'description', 'monthly_price_amount', 'yearly_price_amount', 'max_rooms', 'max_properties',
    'max_staff', 'monthly_message_quota', 'monthly_ai_credit_quota', 'features', 'is_active', 'sort_order',
])]
#[UseFactory(PlanFactory::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'features' => '[]',
        'is_active' => true,
        'sort_order' => 0,
    ];

    public function hasFeature(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features, true);
    }

    public function priceFor(BillingCycle $cycle): ?int
    {
        return match ($cycle) {
            BillingCycle::Monthly => $this->monthly_price_amount,
            BillingCycle::Yearly => $this->yearly_price_amount,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'monthly_price_amount' => RupiahCast::class,
            'yearly_price_amount' => RupiahCast::class,
            'max_rooms' => 'integer',
            'max_properties' => 'integer',
            'max_staff' => 'integer',
            'monthly_message_quota' => 'integer',
            'monthly_ai_credit_quota' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
