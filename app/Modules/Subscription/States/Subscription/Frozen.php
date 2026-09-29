<?php

namespace App\Modules\Subscription\States\Subscription;

final class Frozen extends SubscriptionState
{
    /** @var string */
    public static $name = 'frozen';

    public function getLabel(): string
    {
        return 'Dibekukan';
    }

    public function getColor(): string
    {
        return 'danger';
    }

    public function isWritable(): bool
    {
        return false;
    }
}
