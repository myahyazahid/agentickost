<?php

namespace App\Modules\Property\Concerns;

use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models with a property_id: limits lists to the properties a user may
 * see (FR-USR-02). Policies check single records with isAccessibleBy().
 *
 * @property string $property_id
 */
trait BelongsToProperty
{
    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function isAccessibleBy(User $user): bool
    {
        return $this->property()->firstOrFail()->isAccessibleBy($user);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        if ($user->seesAllProperties()) {
            return;
        }

        $query->whereIn(
            $query->qualifyColumn('property_id'),
            Property::query()->accessibleBy($user)->select('properties.id'),
        );
    }
}
