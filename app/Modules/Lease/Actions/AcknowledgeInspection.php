<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Inspection;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Records that the resident agreed to the inspection, for example after
 * reading it on the caretaker's phone (FR-SIK-01). From the resident portal
 * (M1.5.3) the resident will confirm it themselves.
 */
final class AcknowledgeInspection extends Action
{
    public function handle(Inspection $inspection): Inspection
    {
        $this->authorize('inspect', $inspection->contract()->firstOrFail());

        if ($inspection->resident_acknowledged_at !== null) {
            throw ValidationException::withMessages(['inspection' => 'Penghuni sudah menyetujui pemeriksaan ini.']);
        }

        return $this->transaction(function () use ($inspection): Inspection {
            $inspection->resident_acknowledged_at = now();
            $inspection->save();

            return $inspection;
        });
    }
}
