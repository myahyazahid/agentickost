<?php

namespace App\Modules\Subscription\Filament\App;

use App\Modules\Subscription\Filament\App\Pages\SubscriptionPage;
use App\Modules\Subscription\Support\CurrentSubscription;
use App\Modules\Tenancy\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * A strip across the top of the app panel while the tenant is read-only
 * (FR-SUB-04), so every user knows why saving is refused before trying.
 */
final class ReadOnlyBanner
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function render(): ?Htmlable
    {
        if (Filament::getCurrentPanel()?->getId() !== 'app' || ! $this->tenants->has()) {
            return null;
        }

        if (CurrentSubscription::find()?->status->isWritable() !== false) {
            return null;
        }

        return new HtmlString(Blade::render(<<<'BLADE'
            <div role="status" class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-amber-300 bg-amber-100 px-4 py-2.5 text-sm text-amber-950">
                <p class="min-w-0 flex-1">
                    <strong class="font-semibold">Mode baca saja.</strong>
                    Langganan belum dibayar, jadi data hanya bisa dilihat dan diekspor. Tagihan penghuni tidak terbit otomatis.
                </p>
                <a href="{{ $url }}" class="inline-flex min-h-11 items-center rounded-md bg-amber-950 px-3 font-medium text-white hover:bg-amber-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950">
                    Buka Langganan
                </a>
            </div>
            BLADE, ['url' => SubscriptionPage::getUrl(panel: 'app')]));
    }
}
