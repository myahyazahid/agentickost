<?php

namespace App\Modules\Property;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Property\Enums\PropertyPermission;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertySetting;
use App\Modules\Property\Models\PropertyUser;
use App\Modules\Property\Models\Room;
use App\Modules\Property\Models\RoomPrice;
use App\Modules\Property\Models\RoomType;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class PropertyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(PropertyPermission::class),
        );
    }

    public function boot(): void
    {
        parent::boot();

        Relation::enforceMorphMap([
            'property' => Property::class,
            'property_user' => PropertyUser::class,
            'property_setting' => PropertySetting::class,
            'room_type' => RoomType::class,
            'room' => Room::class,
            'room_price' => RoomPrice::class,
        ]);
    }
}
