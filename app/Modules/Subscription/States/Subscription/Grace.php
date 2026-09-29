<?php

namespace App\Modules\Subscription\States\Subscription;

final class Grace extends SubscriptionState
{
    /** @var string */
    public static $name = 'grace';

    public function getLabel(): string
    {
        return 'Masa tenggang';
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
