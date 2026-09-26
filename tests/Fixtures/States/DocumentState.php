<?php

namespace Tests\Fixtures\States;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;
use Tests\Fixtures\FixtureDocument;

/**
 * Example state machine: draft -> published -> archived, or draft -> archived.
 *
 * @extends State<FixtureDocument>
 */
abstract class DocumentState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Published::class)
            ->allowTransition(Published::class, Archived::class)
            ->allowTransition(Draft::class, Archived::class);
    }
}
