<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Models\Expense;
use App\Modules\Property\Models\Property;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonInterface;

/**
 * Writes an expense and announces it, so its journal is posted in the same
 * transaction (FR-ACC-03). For the RecordExpense action and for other
 * modules, such as a confirmed maintenance ticket (FR-MNT-04).
 */
final class Expenses
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra columns, such as ticket_id
     */
    public function record(
        Property $property,
        string $expenseAccountId,
        string $paidFromAccountId,
        int $amount,
        CarbonInterface $spentOn,
        string $description,
        array $attributes = [],
    ): Expense {
        $actor = $this->actors->current();

        $expense = Expense::create([
            'property_id' => $property->id,
            'expense_account_id' => $expenseAccountId,
            'paid_from_account_id' => $paidFromAccountId,
            'amount' => $amount,
            'spent_on' => $spentOn->toDateString(),
            'description' => mb_substr($description, 0, 255),
            'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ...$attributes,
        ]);

        ExpenseRecorded::dispatch($expense);

        return $expense;
    }
}
