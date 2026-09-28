<?php

namespace App\Modules\Finance\Journal;

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Property\Models\Property;
use App\Support\Actors\ActorContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Posts double-entry journals (FR-ACC-02). Lines on the same account,
 * contract, and property are netted first; an entry whose debits and
 * credits differ is refused (NFR-QA-02), and so is a date in a closed period
 * (PRD §8.11). Call inside the transaction of the business event.
 */
final class JournalPoster
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  list<JournalLineDraft>  $lines
     * @return JournalEntry|null Null when the lines cancel out to nothing
     */
    public function post(
        JournalEvent $event,
        CarbonInterface $date,
        string $description,
        ?Model $source,
        ?Property $property,
        array $lines,
        ?JournalEntry $reversalOf = null,
    ): ?JournalEntry {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Jurnal hanya boleh dibuat di dalam transaksi peristiwanya.');
        }

        $lines = self::net($lines, $property?->id);

        if ($lines === []) {
            return null;
        }

        $total = array_sum(array_map(fn (JournalLineDraft $line): int => $line->amount, $lines));

        if ($total !== 0) {
            throw new LogicException("Jurnal {$event->value} tidak seimbang: selisih debit dan kredit {$total}.");
        }

        $period = FiscalPeriod::for($date);

        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'date' => "Periode {$period->label()} sudah ditutup. Transaksi bertanggal di periode itu tidak bisa dicatat.",
            ]);
        }

        $actor = $this->actors->current();

        $entry = JournalEntry::create([
            'fiscal_period_id' => $period->id,
            'property_id' => $property?->id,
            'number' => $this->numbers->next(DocumentType::Journal, $date, $property?->code),
            'entry_date' => $date->toDateString(),
            'event' => $event,
            'description' => mb_substr($description, 0, 255),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reversal_of_id' => $reversalOf?->id,
            'created_by_type' => $actor->type,
            'created_by_id' => $actor->id,
        ]);

        foreach ($lines as $line) {
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line->accountId,
                'property_id' => $line->propertyId,
                'contract_id' => $line->contractId,
                'debit_amount' => max(0, $line->amount),
                'credit_amount' => max(0, -$line->amount),
                'memo' => $line->memo,
            ]);
        }

        return $entry;
    }

    /**
     * Posts the mirror image of an entry, once.
     */
    public function reverse(JournalEntry $entry, JournalEvent $event, CarbonInterface $date, string $description): JournalEntry
    {
        $existing = $entry->reversal()->first();

        if ($existing !== null) {
            return $existing;
        }

        $lines = array_values($entry->lines()->get()->map(fn (JournalLine $line): JournalLineDraft => new JournalLineDraft(
            $line->account_id,
            $line->credit_amount - $line->debit_amount,
            $line->contract_id,
            $line->property_id,
            $line->memo,
        ))->all());

        $property = $entry->property()->first();

        return $this->post($event, $date, $description, $entry->source()->getResults(), $property, $lines, $entry)
            ?? throw new LogicException('Jurnal pembalik kosong.');
    }

    /**
     * Reverses every entry posted for the given sources that is not reversed
     * yet and is not itself a reversal.
     *
     * @param  list<Model>  $sources
     */
    public function reverseAllFor(array $sources, JournalEvent $event, CarbonInterface $date, string $description): void
    {
        foreach ($sources as $source) {
            $entries = JournalEntry::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->whereNull('reversal_of_id')
                ->whereDoesntHave('reversal')
                ->orderBy('id')
                ->get();

            foreach ($entries as $entry) {
                $this->reverse($entry, $event, $date, $description);
            }
        }
    }

    /**
     * @param  list<JournalLineDraft>  $lines
     * @return list<JournalLineDraft>
     */
    private static function net(array $lines, ?string $propertyId): array
    {
        $netted = [];

        foreach ($lines as $line) {
            $key = implode('|', [$line->accountId, $line->contractId, $line->propertyId ?? $propertyId]);
            $amount = ($netted[$key]->amount ?? 0) + $line->amount;
            $netted[$key] = new JournalLineDraft($line->accountId, $amount, $line->contractId, $line->propertyId ?? $propertyId, $line->memo);
        }

        return array_values(array_filter($netted, fn (JournalLineDraft $line): bool => $line->amount !== 0));
    }
}
