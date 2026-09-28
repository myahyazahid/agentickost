<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Lease\Database\Factories\InspectionItemFactory;
use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checked item of an inspection. At check-out a damaged or missing item
 * can carry a charge, which goes on the final invoice.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $inspection_id
 * @property string|null $asset_id
 * @property string $item_name
 * @property ItemCondition $condition
 * @property int $charge_amount
 * @property string|null $notes
 * @property int $sort_order
 */
#[Fillable(['inspection_id', 'item_name', 'condition', 'charge_amount', 'notes', 'sort_order'])]
#[UseFactory(InspectionItemFactory::class)]
class InspectionItem extends Model
{
    /** @use HasFactory<InspectionItemFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'charge_amount' => 0,
        'sort_order' => 0,
    ];

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'condition' => ItemCondition::class,
            'charge_amount' => RupiahCast::class,
            'sort_order' => 'integer',
        ];
    }
}
