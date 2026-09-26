<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Property\Database\Factories\RoomPriceFactory;
use App\Modules\Property\Enums\RentalPeriod;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of the price history (FR-KMR-01, FR-KMR-03). Exactly one of
 * room_type_id and room_id is set; a room price overrides its type's price.
 * Rows are closed with effective_until, never edited in place.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $room_type_id
 * @property string|null $room_id
 * @property RentalPeriod $rental_period
 * @property int $amount
 * @property Carbon $effective_from
 * @property Carbon|null $effective_until
 * @property string|null $created_by
 */
#[Fillable(['room_type_id', 'room_id', 'rental_period', 'amount', 'effective_from', 'effective_until', 'created_by'])]
#[UseFactory(RoomPriceFactory::class)]
class RoomPrice extends Model
{
    /** @use HasFactory<RoomPriceFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'rental_period' => RentalPeriod::class,
            'amount' => RupiahCast::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }
}
