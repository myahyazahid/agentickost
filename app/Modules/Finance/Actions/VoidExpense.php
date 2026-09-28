<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Events\ExpenseVoided;
use App\Modules\Finance\Models\Expense;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a wrong expense with a reason; its journal is reversed
 * (PRD §8.10). A corrected expense is recorded again.
 */
final class VoidExpense extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Expense $expense, array $input): Expense
    {
        $this->authorize('void', $expense);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($expense, $data): Expense {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->isVoided()) {
                throw ValidationException::withMessages(['reason' => 'Pengeluaran ini sudah dibatalkan.']);
            }

            $expense->voided_at = now();
            $expense->void_reason = $data['reason'];
            $expense->save();

            ExpenseVoided::dispatch($expense);

            return $expense;
        });
    }
}
