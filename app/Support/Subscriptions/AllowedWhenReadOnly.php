<?php

namespace App\Support\Subscriptions;

/**
 * Marks an Action that still runs while the tenant is read-only: paying or
 * changing the subscription, exporting data, and platform provisioning.
 */
interface AllowedWhenReadOnly {}
