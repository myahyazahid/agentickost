<?php

namespace App\Modules\Subscription\States\SubscriptionInvoice;

use App\Modules\Subscription\Models\SubscriptionInvoice;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<SubscriptionInvoice>
 */
abstract class SubscriptionInvoiceState extends State implements HasColor, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    /**
     * Labels by stored value, for filters.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::getStateMapping()->keys() as $name) {
            $state = self::make($name, new SubscriptionInvoice);
            $options[$name] = $state instanceof self ? $state->getLabel() : $name;
        }

        return $options;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Unpaid::class)
            ->allowTransition(Unpaid::class, Paid::class)
            ->allowTransition(Unpaid::class, Voided::class);
    }
}
