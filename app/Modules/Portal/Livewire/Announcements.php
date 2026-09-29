<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Portal\Support\PortalQueries;
use Illuminate\Contracts\View\View;

/**
 * Announcements of the property the resident lives in (FR-PRT-05).
 */
class Announcements extends PortalPage
{
    public function mount(): void
    {
        abort_unless($this->access()->isResident, 404);
    }

    public function render(): View
    {
        return $this->page('portal::livewire.announcements', 'Pengumuman', [
            'announcements' => PortalQueries::announcements($this->access())->with('property')->limit(30)->get(),
        ]);
    }
}
