<?php

namespace App\Modules\Reports\Support;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invoices past their due date with something left to pay, in the
 * properties a user can see. "Past due" is judged by each property's own
 * calendar (NFR-LOC-02).
 */
final class Overdue
{
    /**
     * @return Builder<Invoice>
     */
    public static function invoices(User $user): Builder
    {
        $properties = Property::query()->accessibleBy($user)->get();

        return Invoice::query()
            ->whereIn('status', InvoiceState::openValues())
            ->where('balance_amount', '>', 0)
            ->where(function (Builder $query) use ($properties): void {
                $query->whereRaw('1 = 0');

                foreach ($properties as $property) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('property_id', $property->id)
                        ->whereDate('due_date', '<', $property->today()));
                }
            });
    }
}
