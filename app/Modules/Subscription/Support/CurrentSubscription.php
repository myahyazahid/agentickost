<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Subscription\Models\Subscription;

/**
 * The current tenant's subscription. A tenant starts on a trial without a
 * plan; its subscription row is made the first time it is needed.
 */
final class CurrentSubscription
{
    public static function get(): Subscription
    {
        return self::find() ?? Subscription::query()->createOrFirst();
    }

    /**
     * The row if it exists yet, without making one. For checks that run on
     * every write, which must not write themselves: no row means a trial.
     */
    public static function find(): ?Subscription
    {
        return Subscription::query()->first();
    }
}
