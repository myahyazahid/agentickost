<?php

namespace App\Modules\Subscription\States\Subscription;

final class Restricted extends SubscriptionState
{
    /** @var string */
    public static $name = 'read_only';

    public function getLabel(): string
    {
        return 'Baca saja';
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
