<?php

namespace App\Modules\Subscription\States\SubscriptionInvoice;

final class Paid extends SubscriptionInvoiceState
{
    /** @var string */
    public static $name = 'paid';

    public function getLabel(): string
    {
        return 'Lunas';
    }

    public function getColor(): string
    {
        return 'success';
    }
}
