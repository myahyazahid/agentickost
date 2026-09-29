<?php

namespace App\Support\Actions;

use App\Support\Actors\ActorContext;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use App\Support\Subscriptions\SubscriptionGate;
use BackedEnum;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * One class per business operation. The Filament panels, the API, and agents
 * all call the same action.
 *
 * A handle() method authorizes the current actor, validates its input, then
 * writes inside transaction().
 */
abstract class Action
{
    /**
     * @throws AuthorizationException
     */
    protected function authorize(string $ability, mixed $arguments = []): void
    {
        app(ActorContext::class)->authorize($ability, $arguments);
    }

    /**
     * Enum values are turned into their scalar value first, so input from a
     * Filament select (which may hold enum instances) validates the same as
     * input from the API or an agent.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validate(array $input, array $rules): array
    {
        return Validator::make(self::scalarEnums($input), $rules)->validate();
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private static function scalarEnums(array $input): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            $value instanceof BackedEnum => $value->value,
            is_array($value) => self::scalarEnums($value),
            default => $value,
        }, $input);
    }

    /**
     * Runs the writes in one transaction. A tenant in read-only mode
     * (FR-SUB-04) may not write, except through Actions marked
     * AllowedWhenReadOnly, such as paying the subscription.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback): mixed
    {
        if (! $this instanceof AllowedWhenReadOnly && app()->bound(SubscriptionGate::class)) {
            app(SubscriptionGate::class)->ensureWritable();
        }

        return DB::transaction($callback);
    }
}
