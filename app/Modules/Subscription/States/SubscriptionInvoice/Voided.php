<?php

namespace App\Modules\Subscription\States\SubscriptionInvoice;

final class Voided extends SubscriptionInvoiceState
{
    /** @var string */
    public static $name = 'void';

    public function getLabel(): string
    {
        return 'Dibatalkan';
    }

    public function getColor(): string
    {
        return 'gray';
    }
}
