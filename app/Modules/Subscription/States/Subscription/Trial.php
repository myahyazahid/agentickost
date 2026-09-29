<?php

namespace App\Modules\Subscription\States\Subscription;

final class Trial extends SubscriptionState
{
    /** @var string */
    public static $name = 'trial';

    public function getLabel(): string
    {
        return 'Trial';
    }

    public function getColor(): string
    {
        return 'info';
    }
}
