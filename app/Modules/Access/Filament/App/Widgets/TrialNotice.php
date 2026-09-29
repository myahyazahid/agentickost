<?php

namespace App\Modules\Access\Filament\App\Widgets;

use App\Modules\Access\Enums\AccessPermission;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\TenantContext;
use Filament\Widgets\Widget;

/**
 * How long the trial still runs (FR-TNT-03), for the owner. Paid plans and
 * what happens after the trial come with subscriptions (M1.5.1).
 */
class TrialNotice extends Widget
{
    protected string $view = 'access::widgets.trial-notice';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $tenants = app(TenantContext::class);

        return $tenants->has()
            && User::current()->can(AccessPermission::ManageUsers->value)
            && in_array($tenants->tenant()->status(), [TenantStatus::Trial, TenantStatus::TrialEnded], true);
    }

    /**
     * @return array{heading: string, description: string}
     */
    protected function getViewData(): array
    {
        $tenant = app(TenantContext::class)->tenant();
        $endsOn = $tenant->trial_ends_at?->timezone($tenant->default_timezone)->translatedFormat('j F Y');

        return $tenant->status() === TenantStatus::Trial
            ? ['heading' => "Masa trial: sisa {$tenant->trialDaysLeft()} hari", 'description' => "Trial berakhir {$endsOn}."]
            : ['heading' => 'Masa trial sudah berakhir', 'description' => "Trial berakhir {$endsOn}. Hubungi tim KostPilot untuk melanjutkan."];
    }
}
