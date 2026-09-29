<?php

namespace App\Modules\Access\Filament\App;

use App\Modules\Access\Support\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * A strip across the top of the app panel while a super admin works inside
 * a tenant (FR-TNT-05), with the way back to the admin panel. The admin
 * should never forget whose data is on screen.
 */
final class ImpersonationBanner
{
    public function __construct(private readonly Impersonation $impersonation) {}

    public function render(): ?Htmlable
    {
        if (Filament::getCurrentPanel()?->getId() !== 'app') {
            return null;
        }

        $log = $this->impersonation->current();

        if ($log === null) {
            return null;
        }

        return new HtmlString(Blade::render(<<<'BLADE'
            <div role="status" class="sticky top-0 z-40 flex flex-wrap items-center gap-x-4 gap-y-2 bg-gray-950 px-4 py-2.5 text-sm text-white">
                <p class="min-w-0 flex-1">
                    Anda di dalam <strong class="font-semibold">{{ $tenant }}</strong> sebagai super admin {{ $admin }}.
                    Setiap perubahan tercatat atas nama Anda dan terlihat oleh owner.
                </p>
                <form method="POST" action="{{ route('access.impersonation.end') }}">
                    @csrf
                    <button type="submit" class="min-h-11 rounded-md bg-white px-3 font-medium text-gray-950 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                        Keluar ke panel admin
                    </button>
                </form>
            </div>
            BLADE, [
            'tenant' => $log->tenant()->value('name'),
            'admin' => $this->impersonation->admin()?->name,
        ]));
    }
}
