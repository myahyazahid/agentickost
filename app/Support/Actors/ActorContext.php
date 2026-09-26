<?php

namespace App\Support\Actors;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;

/**
 * Holds the actor of the current request, job, or command.
 *
 * The actor is copied into Laravel's Context so queued jobs run as the actor
 * that dispatched them.
 */
#[Scoped]
final class ActorContext
{
    public const CONTEXT_KEY = 'actor';

    private ?Actor $actor = null;

    public function set(Actor $actor): void
    {
        $this->actor = $actor;

        Context::addHidden(self::CONTEXT_KEY, $actor->toArray());
    }

    public function forget(): void
    {
        $this->actor = null;

        Context::forgetHidden(self::CONTEXT_KEY);
    }

    /**
     * The explicit actor, else the logged-in user, else the system.
     */
    public function current(): Actor
    {
        if ($this->actor !== null) {
            return $this->actor;
        }

        $user = Auth::guard('web')->user();

        return $user !== null ? Actor::user($user) : Actor::system();
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function actingAs(Actor $actor, Closure $callback): mixed
    {
        $previous = $this->actor;

        $this->set($actor);

        try {
            return $callback();
        } finally {
            $previous !== null ? $this->set($previous) : $this->forget();
        }
    }

    /**
     * The system actor passes only when it was set explicitly, so an
     * unauthenticated request never falls through to system privileges.
     *
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed $arguments = []): void
    {
        $actor = $this->current();

        if ($actor->type === ActorType::System) {
            if ($this->actor === null) {
                throw new AuthorizationException('Aksi ini membutuhkan pengguna yang login.');
            }

            return;
        }

        if ($actor->type !== ActorType::User) {
            throw new AuthorizationException("Otorisasi untuk aktor {$actor->type->value} belum tersedia.");
        }

        Gate::forUser($this->resolveUser($actor))->authorize($ability, $arguments);
    }

    /**
     * @throws AuthorizationException
     */
    private function resolveUser(Actor $actor): Authenticatable
    {
        $user = $actor->user ?? Auth::createUserProvider('users')?->retrieveById($actor->id);

        if ($user === null) {
            throw new AuthorizationException('Pengguna tidak ditemukan.');
        }

        return $user;
    }
}
