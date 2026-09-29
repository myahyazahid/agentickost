<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Access\Audit\AuditLogger;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\ResidentRegister;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Support\Facades\Gate;

/**
 * The resident list of a property for the RT/RW (FR-PNH-07), as a file to
 * hand over. Staff who may see identities get the identity numbers, and the
 * export is written to the audit log (NFR-PDP-04). Allowed in read-only
 * mode, since it changes no data.
 */
final class ExportResidentRegister extends Action implements AllowedWhenReadOnly
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @return list<array{no: int, name: string, gender: ?string, birth_date: ?string, identity: ?string, phone: string, institution: ?string, vehicle: ?string, room: ?string, since: ?string}>
     */
    public function handle(Property $property): array
    {
        $this->authorize('viewAny', Resident::class);
        $this->authorize('view', $property);

        $user = $this->actors->current()->user;
        $withIdentity = $user !== null && Gate::forUser($user)->allows(LeasePermission::ViewIdentity->value);
        $rows = ResidentRegister::rows($property, $withIdentity);

        if ($withIdentity) {
            $this->transaction(fn () => $this->audit->record('resident.register_exported', $property, null, ['residents' => count($rows)]));
        }

        return $rows;
    }
}
