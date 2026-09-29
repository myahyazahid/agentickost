<?php

namespace App\Support\Filament;

use App\Support\Subscriptions\ReadOnlyMode;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Bridges domain Actions and Filament. Actions validate with plain keys
 * ("code"); Filament forms live under a state path ("data.code").
 */
final class DomainActions
{
    /**
     * Run a domain Action from a page form, showing its validation errors on
     * the matching fields.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function forForm(Closure $callback, string $statePath = 'data'): mixed
    {
        try {
            return $callback();
        } catch (ReadOnlyMode $exception) {
            self::notifyReadOnly($exception);

            throw new Halt;
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $messages, string $key): array => ["{$statePath}.{$key}" => $messages])
                    ->all(),
            );
        }
    }

    /**
     * Run a domain Action from a Filament action (a modal or a row button).
     * Validation errors become a notification and the action stops.
     *
     * @param  Closure(): mixed  $callback
     */
    public static function forAction(Action $action, Closure $callback): void
    {
        try {
            $callback();
        } catch (ReadOnlyMode $exception) {
            self::notifyReadOnly($exception);

            $action->halt();
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('Belum bisa diproses')
                ->body(implode(' ', Arr::flatten($exception->errors())))
                ->send();

            $action->halt();
        }
    }

    private static function notifyReadOnly(ReadOnlyMode $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Mode baca saja')
            ->body($exception->getMessage())
            ->persistent()
            ->send();
    }
}
