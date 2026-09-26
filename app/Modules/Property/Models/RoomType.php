<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Property\Database\Factories\RoomTypeFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $name
 * @property string|null $description
 * @property int $default_capacity
 * @property list<string>|null $facilities
 */
#[Fillable(['property_id', 'name', 'description', 'default_capacity', 'facilities'])]
#[UseFactory(RoomTypeFactory::class)]
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'default_capacity' => 1,
    ];

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<RoomPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(RoomPrice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_capacity' => 'integer',
            'facilities' => 'array',
        ];
    }
}
