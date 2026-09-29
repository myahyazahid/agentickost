<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Undoes a manual journal with its mirror image, dated when the mistake is
 * corrected (PRD §8.10). Automatic journals are corrected through their
 * documents instead.
 */
final class ReverseManualJournal extends Action
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  array<string, mixed>  $input  entry_date, reason
     */
    public function handle(JournalEntry $entry, array $input): JournalEntry
    {
        $this->authorize('reverseManual', $entry);

        $today = CarbonImmutable::now($this->tenants->tenant()->default_timezone)->toDateString();

        $data = $this->validate($input, [
            'entry_date' => ['required', 'date', 'after_or_equal:'.$entry->entry_date->toDateString(), 'before_or_equal:'.$today],
            'reason' => ['required', 'string', 'min:5', 'max:200'],
        ]);

        if ($entry->event !== JournalEvent::Manual || $entry->reversal_of_id !== null) {
            throw ValidationException::withMessages(['reason' => 'Hanya jurnal manual yang bisa dibalik dari sini. Jurnal otomatis dikoreksi lewat dokumennya.']);
        }

        if ($entry->reversal()->exists()) {
            throw ValidationException::withMessages(['reason' => "Jurnal {$entry->number} sudah dibalik."]);
        }

        return $this->transaction(fn (): JournalEntry => $this->poster->reverse(
            $entry,
            JournalEvent::Manual,
            CarbonImmutable::parse($data['entry_date']),
            "Pembalikan {$entry->number}: {$data['reason']}",
        ));
    }
}
