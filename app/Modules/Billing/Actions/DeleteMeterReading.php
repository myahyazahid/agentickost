<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\MeterReading;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Removes a mistyped reading so it can be recorded again. Only the latest
 * reading of a room's meter, and only before it is billed, since the next
 * reading counts from it.
 */
final class DeleteMeterReading extends Action
{
    public function __construct(private readonly AttachmentSync $attachments) {}

    public function handle(MeterReading $reading): void
    {
        $this->authorize('delete', $reading);

        $newer = MeterReading::query()
            ->where('room_id', $reading->room_id)
            ->where('utility', $reading->utility->value)
            ->whereDate('reading_date', '>', $reading->reading_date)
            ->exists();

        if ($newer) {
            throw ValidationException::withMessages(['reading' => 'Hanya pencatatan terakhir kamar ini yang bisa dihapus.']);
        }

        $this->transaction(function () use ($reading): void {
            $this->attachments->sync($reading, AttachmentCollection::Meter, []);
            $reading->delete();
        });
    }
}
