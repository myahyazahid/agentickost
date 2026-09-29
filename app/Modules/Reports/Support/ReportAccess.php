<?php

namespace App\Modules\Reports\Support;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Subscription\Enums\PlanFeature;
use App\Support\Subscriptions\SubscriptionGate;

/**
 * Who may open the advanced reports: those allowed to see the figures, on a
 * plan with advanced reports (FR-SUB-02). Trials see everything.
 */
final class ReportAccess
{
    public static function financialStatements(): bool
    {
        return User::current()->can(FinancePermission::View->value) && self::planAllows();
    }

    public static function receivables(): bool
    {
        return User::current()->can(BillingPermission::ViewInvoices->value) && self::planAllows();
    }

    private static function planAllows(): bool
    {
        return app(SubscriptionGate::class)->allows(PlanFeature::AdvancedReports->value);
    }
}
