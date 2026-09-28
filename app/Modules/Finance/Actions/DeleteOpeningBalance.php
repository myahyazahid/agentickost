<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\OpeningBalance;
use App\Support\Actions\Action;

/**
 * Discards a draft opening balance. A posted one stays; its figures are
 * corrected through the usual documents (PRD §8.10).
 */
final class DeleteOpeningBalance extends Action
{
    public function handle(OpeningBalance $openingBalance): void
    {
        $this->authorize('delete', $openingBalance);

        $this->transaction(function () use ($openingBalance): void {
            $openingBalance->lines()->get()->each->delete();
            $openingBalance->delete();
        });
    }
}
