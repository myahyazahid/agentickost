<?php

namespace App\Modules\Subscription\States\SubscriptionInvoice;

final class Unpaid extends SubscriptionInvoiceState
{
    /** @var string */
    public static $name = 'unpaid';

    public function getLabel(): string
    {
        return 'Belum dibayar';
    }

    public function getColor(): string
    {
        return 'warning';
    }
}
