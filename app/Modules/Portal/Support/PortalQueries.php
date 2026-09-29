<?php

namespace App\Modules\Portal\Support;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Payment\Models\Payment;
use App\Modules\Portal\Models\Announcement;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the portal shows, always limited to what the login may see
 * (FR-PRT-02, FR-PRT-07). Portal pages query only through here.
 */
final class PortalQueries
{
    /**
     * Invoices of the visible contracts, drafts left out.
     *
     * @return Builder<Invoice>
     */
    public static function invoices(PortalAccess $access): Builder
    {
        return Invoice::query()
            ->whereIn('contract_id', $access->contractIds())
            ->where('status', '!=', Draft::$name);
    }

    /**
     * @return Builder<Payment>
     */
    public static function payments(PortalAccess $access): Builder
    {
        return Payment::query()->whereIn('contract_id', $access->contractIds());
    }

    /**
     * Repair reports for the rooms a resident lives in, and any they
     * reported themselves, such as one about a common area.
     *
     * @return Builder<Ticket>
     */
    public static function tickets(PortalAccess $access): Builder
    {
        $rooms = Contract::query()->whereIn('id', $access->livingContractIds())->select('room_id');

        return Ticket::query()->where(fn (Builder $query) => $query
            ->whereIn('room_id', $rooms)
            ->orWhere(fn (Builder $query) => $query
                ->where('reported_by_type', ActorType::Resident->value)
                ->whereIn('reported_by_id', $access->residentRecordIds())));
    }

    /**
     * Published announcements of the properties a resident lives in.
     *
     * @return Builder<Announcement>
     */
    public static function announcements(PortalAccess $access): Builder
    {
        $properties = Contract::query()->whereIn('id', $access->livingContractIds())->select('property_id');

        return Announcement::query()
            ->published()
            ->whereIn('property_id', $properties)
            ->orderByDesc('published_at');
    }
}
