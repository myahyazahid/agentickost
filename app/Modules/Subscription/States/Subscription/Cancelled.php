<?php

namespace App\Modules\Subscription\States\Subscription;

final class Cancelled extends SubscriptionState
{
    /** @var string */
    public static $name = 'cancelled';

    public function getLabel(): string
    {
        return 'Dihentikan';
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
