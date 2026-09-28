<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money was spent for a property.
 */
final class ExpenseRecorded
{
    use Dispatchable;

    public function __construct(public readonly Expense $expense) {}
}
