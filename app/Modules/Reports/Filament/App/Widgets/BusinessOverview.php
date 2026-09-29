<?php

namespace App\Modules\Reports\Filament\App\Widgets;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Filament\App\Resources\Journals\JournalEntryResource;
use App\Modules\Maintenance\Enums\MaintenancePermission;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\TicketResource;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Property\Enums\PropertyPermission;
use App\Modules\Property\Filament\App\Resources\Rooms\RoomResource;
use App\Modules\Reports\Filament\App\Pages\Arrears;
use App\Modules\Reports\Support\DashboardFigures;
use App\Modules\Tenancy\TenantContext;
use App\Support\Money\Rupiah;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The owner's daily read of the business (FR-RPT-01). Each figure shows only
 * to staff whose role covers it, and each opens the list behind it.
 */
class BusinessOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -5;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = User::current();

        foreach (self::permissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = User::current();
        $tenant = app(TenantContext::class)->tenant();
        $figures = new DashboardFigures($user, CarbonImmutable::now($tenant->default_timezone));
        $stats = [];

        if ($user->can(PropertyPermission::View->value)) {
            $occupancy = $figures->occupancy();
            $stats[] = $occupancy['rooms'] === 0
                ? Stat::make('Okupansi', 'Belum ada kamar')
                    ->description('Tambahkan kamar di menu Properti')
                    ->url(RoomResource::getUrl('index'))
                : Stat::make('Okupansi', "{$occupancy['percent']}%")
                    ->description("{$occupancy['occupied']} dari {$occupancy['rooms']} kamar terisi".($occupancy['maintenance'] > 0 ? ", {$occupancy['maintenance']} diperbaiki" : ''))
                    ->descriptionIcon(Heroicon::OutlinedHome)
                    ->url(RoomResource::getUrl('index'));
        }

        if ($user->can(FinancePermission::View->value)) {
            $stats[] = Stat::make('Pendapatan bulan ini', Rupiah::format($figures->revenueThisMonth()))
                ->description('Uang diterima: '.Rupiah::format($figures->receivedThisMonth()))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->url(JournalEntryResource::getUrl('index'));
        }

        if ($user->can(BillingPermission::ViewInvoices->value)) {
            $arrears = $figures->arrears();
            $stats[] = Stat::make('Tunggakan', Rupiah::format($arrears['amount']))
                ->description($arrears['contracts'] > 0 ? "{$arrears['contracts']} kontrak lewat jatuh tempo" : 'Tidak ada yang lewat jatuh tempo')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($arrears['amount'] > 0 ? 'danger' : 'success')
                ->url(Arrears::getUrl());
        }

        if ($user->can(MaintenancePermission::ViewTickets->value)) {
            $tickets = $figures->openTickets();
            $stats[] = Stat::make('Tiket terbuka', (string) $tickets['open'])
                ->description($tickets['pressing'] > 0 ? "{$tickets['pressing']} prioritas tinggi atau darurat" : 'Tidak ada yang mendesak')
                ->descriptionIcon(Heroicon::OutlinedWrenchScrewdriver)
                ->color($tickets['pressing'] > 0 ? 'warning' : 'gray')
                ->url(TicketResource::getUrl('index'));
        }

        if ($user->can(PaymentPermission::VerifyPayments->value)) {
            $pending = $figures->pendingPayments();
            $stats[] = Stat::make('Menunggu verifikasi', (string) $pending['count'])
                ->description($pending['count'] > 0 ? 'Senilai '.Rupiah::format($pending['amount']) : 'Semua pembayaran sudah diperiksa')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($pending['count'] > 0 ? 'warning' : 'gray')
                ->url(PaymentResource::getUrl('index', ['tab' => 'verifikasi']));
        }

        return $stats;
    }

    /**
     * @return list<string>
     */
    private static function permissions(): array
    {
        return [
            PropertyPermission::View->value,
            FinancePermission::View->value,
            BillingPermission::ViewInvoices->value,
            MaintenancePermission::ViewTickets->value,
            PaymentPermission::VerifyPayments->value,
        ];
    }
}
