<?php

namespace App\Modules\Property\Filament;

use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\RoomType;

/**
 * Select options limited to what the logged-in user may see.
 */
final class PropertyOptions
{
    /**
     * @return array<string, string>
     */
    public static function properties(): array
    {
        return Property::query()
            ->accessibleBy(User::current())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function roomTypes(?string $propertyId): array
    {
        if ($propertyId === null) {
            return [];
        }

        return RoomType::query()
            ->accessibleBy(User::current())
            ->where('property_id', $propertyId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
