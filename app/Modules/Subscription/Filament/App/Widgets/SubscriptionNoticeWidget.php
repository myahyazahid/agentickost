<?php

namespace App\Modules\Subscription\Filament\App\Widgets;

use App\Modules\Access\Models\User;
use App\Modules\Subscription\Enums\SubscriptionPermission;
use App\Modules\Subscription\Filament\App\Pages\SubscriptionPage;
use App\Modules\Subscription\Support\SubscriptionNotice;
use App\Modules\Tenancy\TenantContext;
use Filament\Widgets\Widget;

/**
 * The trial countdown, an unpaid subscription invoice, or a lapsed
 * subscription, on the owner's dashboard (FR-TNT-03, PRD §9.6).
 */
class SubscriptionNoticeWidget extends Widget
{
    protected string $view = 'subscription::widgets.subscription-notice';

    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return app(TenantContext::class)->has()
            && User::current()->can(SubscriptionPermission::Manage->value)
            && SubscriptionNotice::current() !== null;
    }

    /**
     * @return array{heading: string, description: string, urgent: bool, url: string}
     */
    protected function getViewData(): array
    {
        $notice = SubscriptionNotice::current();

        return [
            'heading' => $notice->heading ?? '',
            'description' => $notice->description ?? '',
            'urgent' => $notice->urgent ?? false,
            'url' => SubscriptionPage::getUrl(),
        ];
    }
}
