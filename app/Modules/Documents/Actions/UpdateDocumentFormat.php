<?php

namespace App\Modules\Documents\Actions;

use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Enums\ResetPeriod;
use App\Modules\Documents\Models\DocumentSequence;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Changes how one kind of document is numbered (FR-BIL-07). The counter
 * carries on from where it was, so a change never hands out a number that
 * was already used in the current period.
 */
final class UpdateDocumentFormat extends Action
{
    private const TOKENS = ['{YYYY}', '{YY}', '{MM}', '{PROP}'];

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(DocumentType $type, array $input): DocumentSequence
    {
        $this->authorize('update', DocumentSequence::class);

        $data = $this->validate($input, [
            'format' => ['required', 'string', 'max:60'],
            'reset_period' => ['required', Rule::enum(ResetPeriod::class)],
        ]);

        $format = trim($data['format']);
        $reset = ResetPeriod::from($data['reset_period']);

        if (($problem = self::problemWith($format, $reset)) !== null) {
            throw ValidationException::withMessages(['format' => $problem]);
        }

        $periodKey = $reset->keyFor(CarbonImmutable::now());

        return $this->transaction(function () use ($type, $format, $reset, $periodKey): DocumentSequence {
            $sequence = DocumentSequence::query()->where('document_type', $type->value)->lockForUpdate()->first()
                ?? new DocumentSequence(['document_type' => $type, 'next_number' => 1, 'current_period_key' => $periodKey]);

            if ($sequence->exists && $sequence->reset_period !== $reset) {
                $sequence->current_period_key = $periodKey;
            }

            $sequence->format = $format;
            $sequence->reset_period = $reset;
            $sequence->save();

            return $sequence;
        });
    }

    /**
     * Why a format cannot be used, or null when it can. A counter that starts
     * again each month or year needs that month or year in the number,
     * otherwise the same number would come back.
     */
    public static function problemWith(string $format, ResetPeriod $reset): ?string
    {
        if (preg_match_all('/\{SEQ(?::[1-9])?\}/', $format) !== 1) {
            return 'Format wajib memuat satu {SEQ} atau {SEQ:4} untuk nomor urut.';
        }

        $leftover = preg_replace('/\{SEQ(?::[1-9])?\}/', '', str_replace(self::TOKENS, '', $format));

        if (str_contains((string) $leftover, '{') || str_contains((string) $leftover, '}')) {
            return 'Ada kode yang tidak dikenal. Kode yang bisa dipakai: {YYYY}, {YY}, {MM}, {PROP}, {SEQ:4}.';
        }

        $hasYear = str_contains($format, '{YYYY}') || str_contains($format, '{YY}');

        return match (true) {
            $reset === ResetPeriod::Monthly && (! $hasYear || ! str_contains($format, '{MM}')) => 'Nomor yang diulang tiap bulan harus memuat tahun dan bulan, misalnya {YYYY}/{MM}.',
            $reset === ResetPeriod::Yearly && ! $hasYear => 'Nomor yang diulang tiap tahun harus memuat tahun, misalnya {YYYY}.',
            default => null,
        };
    }
}
