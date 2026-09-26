<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Property\Database\Factories\PropertyUserFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Assignment of a staff member to a property (FR-USR-02).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $user_id
 */
#[UseFactory(PropertyUserFactory::class)]
class PropertyUser extends Pivot
{
    /** @use HasFactory<PropertyUserFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'property_user';

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
