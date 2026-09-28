<?php

namespace App\Modules\Onboarding;

use App\Modules\Access\Permissions\PermissionRegistry;
use App\Modules\Onboarding\Enums\OnboardingPermission;
use App\Support\Modules\ModuleServiceProvider;

/**
 * Getting a tenant started (PRD §7.2): the setup wizard, the spreadsheet
 * import, and the checklist. It owns no tables; everything it creates goes
 * through the Actions of the module that owns the data.
 */
class OnboardingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving(
            PermissionRegistry::class,
            fn (PermissionRegistry $registry) => $registry->register(OnboardingPermission::class),
        );
    }
}
