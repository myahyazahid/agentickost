<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Access\Models\StaffInvitation;
use App\Modules\Access\Models\User;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Modules\Subscription\Models\Plan;
use App\Support\Subscriptions\SubscriptionGate;

/**
 * What the current tenant uses of the resources a plan limits (FR-SUB-01).
 * Staff counts active accounts plus invitations not yet answered, since
 * each will become an account.
 */
final class SubscriptionUsage
{
    public static function of(string $resource): int
    {
        return match ($resource) {
            SubscriptionGate::ROOMS => Room::query()->count(),
            SubscriptionGate::PROPERTIES => Property::query()->count(),
            SubscriptionGate::STAFF => User::query()->where('is_active', true)->count()
                + StaffInvitation::query()->open()->where('expires_at', '>', now())->count(),
            default => 0,
        };
    }

    public static function limit(Plan $plan, string $resource): ?int
    {
        return match ($resource) {
            SubscriptionGate::ROOMS => $plan->max_rooms,
            SubscriptionGate::PROPERTIES => $plan->max_properties,
            SubscriptionGate::STAFF => $plan->max_staff,
            default => null,
        };
    }

    public static function label(string $resource): string
    {
        return match ($resource) {
            SubscriptionGate::ROOMS => 'kamar',
            SubscriptionGate::PROPERTIES => 'properti',
            SubscriptionGate::STAFF => 'pengguna',
            default => $resource,
        };
    }

    /**
     * Resources whose current use is above what the plan allows, as
     * readable lines, for refusing a downgrade (FR-SUB-05).
     *
     * @return list<string>
     */
    public static function exceeding(Plan $plan): array
    {
        $lines = [];

        foreach ([SubscriptionGate::PROPERTIES, SubscriptionGate::ROOMS, SubscriptionGate::STAFF] as $resource) {
            $limit = self::limit($plan, $resource);
            $used = self::of($resource);

            if ($limit !== null && $used > $limit) {
                $lines[] = "{$used} ".self::label($resource).", paket {$plan->name} hanya {$limit}";
            }
        }

        return $lines;
    }
}
