<?php

namespace App\Support\States;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Spatie\ModelStates\State;

/**
 * Runs a state transition from an Action and reports a disallowed one as a
 * validation error the UI can show.
 */
final class StateTransition
{
    /**
     * @template TModel of Model
     *
     * @param  State<TModel>  $state
     * @param  class-string<State<TModel>>  $target
     */
    public static function to(State $state, string $target, string $errorKey = 'status'): void
    {
        try {
            $state->transitionTo($target);
        } catch (CouldNotPerformTransition) {
            $from = self::label($state);
            $to = self::label(new $target($state->getModel()));

            throw ValidationException::withMessages([
                $errorKey => "Status tidak bisa diubah dari {$from} ke {$to}.",
            ]);
        }
    }

    /**
     * @template TModel of Model
     *
     * @param  State<TModel>  $state
     */
    private static function label(State $state): string
    {
        $label = $state instanceof HasLabel ? $state->getLabel() : null;

        return is_string($label) ? $label : $state->getValue();
    }
}
