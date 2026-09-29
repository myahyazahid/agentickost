<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Tenancy\Support\PlatformSettings;

/**
 * How long an unpaid subscription stays in each stage (PRD §9.6), and how
 * tenants pay while there is no payment gateway (FR-SUB-03). Set by super
 * admins in Pengaturan platform.
 */
final class BillingSettings
{
    public const GRACE_DAYS = 'subscription_grace_days';

    public const READ_ONLY_DAYS = 'subscription_read_only_days';

    public const PAYMENT_INSTRUCTIONS = 'subscription_payment_instructions';

    /**
     * Days a renewal may stay unpaid before the tenant becomes read-only.
     */
    public static function graceDays(): int
    {
        $days = PlatformSettings::get(self::GRACE_DAYS);

        return is_int($days) ? $days : 7;
    }

    /**
     * Days a tenant stays read-only before it is frozen.
     */
    public static function readOnlyDays(): int
    {
        $days = PlatformSettings::get(self::READ_ONLY_DAYS);

        return is_int($days) ? $days : 30;
    }

    /**
     * Where tenants transfer subscription payments, shown on unpaid
     * invoices.
     */
    public static function paymentInstructions(): ?string
    {
        $text = PlatformSettings::get(self::PAYMENT_INSTRUCTIONS);

        return is_string($text) && trim($text) !== '' ? $text : null;
    }
}
