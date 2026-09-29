<?php

namespace App\Modules\Portal\Actions;

use App\Modules\Portal\Models\Announcement;
use App\Support\Actions\Action;

/**
 * Removes an announcement from the portal and the staff list.
 */
final class DeleteAnnouncement extends Action
{
    public function handle(Announcement $announcement): void
    {
        $this->authorize('delete', $announcement);

        $this->transaction(fn () => $announcement->delete());
    }
}
