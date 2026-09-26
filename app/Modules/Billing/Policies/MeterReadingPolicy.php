<?php

namespace App\Modules\Billing\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Billing\Models\MeterReading;
use App\Modules\Property\Models\Room;

final class MeterReadingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BillingPermission::ViewInvoices->value)
            || $user->can(BillingPermission::RecordMeterReadings->value);
    }

    public function view(User $user, MeterReading $reading): bool
    {
        return $this->viewAny($user) && $reading->room()->firstOrFail()->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(BillingPermission::RecordMeterReadings->value);
    }

    public function recordFor(User $user, Room $room): bool
    {
        return $user->can(BillingPermission::RecordMeterReadings->value) && $room->isAccessibleBy($user);
    }

    /**
     * A reading can be corrected until it is billed.
     */
    public function update(User $user, MeterReading $reading): bool
    {
        return ! $reading->isBilled() && $this->recordFor($user, $reading->room()->firstOrFail());
    }

    public function delete(User $user, MeterReading $reading): bool
    {
        return $this->update($user, $reading);
    }
}
