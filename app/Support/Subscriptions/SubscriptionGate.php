<?php

namespace App\Support\Subscriptions;

use Illuminate\Validation\ValidationException;

/**
 * What the tenant's subscription allows (FR-SUB-01, FR-SUB-02, FR-SUB-04).
 * Declared here so modules can ask without depending on the Subscription
 * module, which binds the implementation. Without a binding everything is
 * allowed.
 */
interface SubscriptionGate
{
    public const ROOMS = 'rooms';

    public const PROPERTIES = 'properties';

    public const STAFF = 'staff';

    /**
     * @throws ReadOnlyMode when the current tenant may not change data
     */
    public function ensureWritable(): void;

    /**
     * Refuses adding $count of a resource beyond the plan's limit, with the
     * message on $errorKey so a form shows it on the field that adds it.
     *
     * @param  self::ROOMS|self::PROPERTIES|self::STAFF  $resource
     *
     * @throws ValidationException
     */
    public function ensureCanAdd(string $resource, int $count = 1, string $errorKey = 'plan'): void;

    public function allows(string $feature): bool;
}
