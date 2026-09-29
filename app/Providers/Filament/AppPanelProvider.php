<?php

namespace App\Providers\Filament;

use App\Modules\Access\Filament\App\Auth\Register;
use App\Modules\Access\Http\Middleware\SetTenantContext;
use App\Support\Filament\PanelTheme;
use App\Support\Modules\DiscoversModuleComponents;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel for tenant owners and staff.
 */
class AppPanelProvider extends PanelProvider
{
    use DiscoversModuleComponents;

    public function panel(Panel $panel): Panel
    {
        $panel = PanelTheme::apply($panel)
            ->default()
            ->id('app')
            ->path('app')
            ->login()
            ->registration(Register::class)
            ->emailVerification()
            ->passwordReset()
            ->databaseNotifications()
            // Daily work first, setup last.
            ->navigationGroups(['Penghuni', 'Tagihan', 'Pembayaran', 'Maintenance', 'Keuangan', 'Properti'])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent so Livewire requests from the panel also get the tenant context.
            ->authMiddleware([
                Authenticate::class,
                SetTenantContext::class,
            ], isPersistent: true);

        return $this->discoverModuleComponents($panel, 'App');
    }
}
