<?php

namespace App\Modules\Lease\States\Contract;

use App\Modules\Lease\Models\Contract;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Contract status (PRD §9.3). A renewal is a new contract linked to the old
 * one; the old one completes when the renewal starts.
 *
 * @extends State<Contract>
 */
abstract class ContractState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    /**
     * Whether the resident still lives in the room under this contract.
     */
    public function isRunning(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, Notice::class)
            ->allowTransition(Notice::class, Active::class)
            ->allowTransition(Active::class, Completed::class)
            ->allowTransition(Notice::class, Completed::class)
            ->allowTransition(Active::class, Terminated::class)
            ->allowTransition(Notice::class, Terminated::class);
    }

    /**
     * @return list<string>
     */
    public static function runningValues(): array
    {
        return [Active::$name, Notice::$name];
    }
}
