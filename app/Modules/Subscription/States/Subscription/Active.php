<?php

namespace App\Modules\Subscription\States\Subscription;

final class Active extends SubscriptionState
{
    /** @var string */
    public static $name = 'active';

    public function getLabel(): string
    {
        return 'Aktif';
    }

    public function getColor(): string
    {
        return 'success';
    }
}
