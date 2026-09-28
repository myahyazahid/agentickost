<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A recorded expense was cancelled with a reason.
 */
final class ExpenseVoided
{
    use Dispatchable;

    public function __construct(public readonly Expense $expense) {}
}
