<?php

namespace App\Modules\Documents\Support;

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Hands out document numbers per tenant (FR-BIL-07, schema §14.3).
 *
 * Call inside the transaction that creates the document: the sequence row is
 * locked until it commits, and a rollback gives the number back.
 */
final class DocumentNumbers
{
    public function next(DocumentType $type, CarbonInterface $date, ?string $propertyCode = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Nomor dokumen hanya boleh diambil di dalam transaksi.');
        }

        $sequence = $this->lockedSequence($type, $date);
        $periodKey = $sequence->reset_period->keyFor($date);

        if ($sequence->current_period_key !== $periodKey) {
            $sequence->current_period_key = $periodKey;
            $sequence->next_number = 1;
        }

        $number = $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        return $this->render($sequence->format, $number, $date, $propertyCode);
    }

    /**
     * The number the next document would get on a date, without taking it.
     */
    public function preview(DocumentType $type, CarbonInterface $date, ?string $propertyCode = null): string
    {
        $sequence = DocumentSequence::query()->where('document_type', $type->value)->first();

        if ($sequence === null) {
            return $this->render($type->defaultFormat(), 1, $date, $propertyCode);
        }

        $number = $sequence->current_period_key === $sequence->reset_period->keyFor($date) ? $sequence->next_number : 1;

        return $this->render($sequence->format, $number, $date, $propertyCode);
    }

    public function render(string $format, int $number, CarbonInterface $date, ?string $propertyCode = null): string
    {
        $rendered = strtr($format, [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{PROP}' => $propertyCode ?? '',
        ]);

        return (string) preg_replace_callback(
            '/\{SEQ(?::(\d+))?\}/',
            fn (array $match): string => str_pad((string) $number, (int) ($match[1] ?? 1), '0', STR_PAD_LEFT),
            $rendered,
        );
    }

    private function lockedSequence(DocumentType $type, CarbonInterface $date): DocumentSequence
    {
        DocumentSequence::query()->firstOrCreate(
            ['document_type' => $type->value],
            [
                'format' => $type->defaultFormat(),
                'reset_period' => $type->defaultResetPeriod(),
                'current_period_key' => $type->defaultResetPeriod()->keyFor($date),
                'next_number' => 1,
            ],
        );

        return DocumentSequence::query()
            ->where('document_type', $type->value)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
