<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Property\Database\Factories\RoomFactory;
use App\Modules\Property\States\Room\RoomState;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\States\EnforcesStateTransitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\ModelStates\HasStates;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $room_type_id
 * @property string $number
 * @property string|null $floor
 * @property int $capacity
 * @property RoomState $status
 * @property list<string>|null $facilities
 * @property string|null $notes
 */
#[Fillable(['property_id', 'room_type_id', 'number', 'floor', 'capacity', 'facilities', 'notes'])]
#[UseFactory(RoomFactory::class)]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, EnforcesStateTransitions, HasAttachments, HasFactory, HasStates, HasUlids, SoftDeletes;

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Price overrides for this room only.
     *
     * @return HasMany<RoomPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(RoomPrice::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'status' => RoomState::class,
            'facilities' => 'array',
        ];
    }
}
