<?php

namespace App\Modules\Subscription\States\Subscription;

use App\Modules\Subscription\Models\Subscription;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Subscription status (PRD §9.6). An unpaid subscription moves from grace
 * to read-only to frozen; paying brings it back to active from any of
 * those. A cancelled subscription runs to the end of its paid period, then
 * becomes read-only.
 *
 * @extends State<Subscription>
 */
abstract class SubscriptionState extends State implements HasColor, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    /**
     * Whether the tenant may change data (FR-SUB-04).
     */
    public function isWritable(): bool
    {
        return true;
    }

    /**
     * Labels by stored value, for filters.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::getStateMapping()->keys() as $name) {
            $state = self::make($name, new Subscription);
            $options[$name] = $state instanceof self ? $state->getLabel() : $name;
        }

        return $options;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Trial::class)
            ->allowTransition(Trial::class, Active::class)
            ->allowTransition(Trial::class, Grace::class)
            ->allowTransition(Active::class, Grace::class)
            ->allowTransition(Active::class, Cancelled::class)
            ->allowTransition(Grace::class, Active::class)
            ->allowTransition(Grace::class, Restricted::class)
            ->allowTransition(Restricted::class, Active::class)
            ->allowTransition(Restricted::class, Frozen::class)
            ->allowTransition(Frozen::class, Active::class)
            ->allowTransition(Cancelled::class, Active::class)
            ->allowTransition(Cancelled::class, Restricted::class);
    }
}
