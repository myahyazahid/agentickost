<?php

namespace App\Support\States;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Spatie\ModelStates\State;

/**
 * Rejects state changes that skip transitionTo(), such as assigning the
 * attribute directly, when the transition is not in the state config.
 *
 * Use together with Spatie\ModelStates\HasStates.
 */
trait EnforcesStateTransitions
{
    public static function bootEnforcesStateTransitions(): void
    {
        static::updating(function (Model $model): void {
            foreach ($model->getCasts() as $field => $cast) {
                if (! is_subclass_of($cast, State::class) || ! $model->isDirty($field)) {
                    continue;
                }

                $from = $model->getRawOriginal($field);
                $to = $model->getAttributes()[$field];

                if ($from !== null && ! $cast::config()->isTransitionAllowed($from, $to)) {
                    throw CouldNotPerformTransition::notFound($from, $to, $model);
                }
            }
        });
    }
}
