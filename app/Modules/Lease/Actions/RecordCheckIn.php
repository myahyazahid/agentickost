<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Events\CheckedIn;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Lease\Support\InspectionItems;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Checks the resident in: records the room's condition item by item with
 * photos, and whether the resident agreed to it (FR-SIK-01). A draft
 * contract is activated at the same time.
 */
final class RecordCheckIn extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
        private readonly ActivateContract $activate,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Inspection
    {
        $this->authorize('inspect', $contract);

        $data = $this->validate($input, [
            'inspected_on' => ['required', 'date'],
            'resident_acknowledged' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['string'],
            ...InspectionItems::rules(withCharges: false),
        ]);

        if (! $contract->status->equals(Draft::class, Active::class)) {
            throw ValidationException::withMessages(['inspected_on' => 'Check-in hanya untuk kontrak draf atau aktif.']);
        }

        if ($contract->inspections()->where('type', InspectionType::CheckIn->value)->exists()) {
            throw ValidationException::withMessages(['inspected_on' => 'Kontrak ini sudah check-in.']);
        }

        if (CarbonImmutable::parse($data['inspected_on'])->greaterThan($contract->property()->firstOrFail()->today())) {
            throw ValidationException::withMessages(['inspected_on' => 'Tanggal check-in tidak boleh di masa depan.']);
        }

        return $this->transaction(function () use ($contract, $data): Inspection {
            if ($contract->status->equals(Draft::class)) {
                $this->activate->handle($contract);
            }

            $inspection = Inspection::create([
                'contract_id' => $contract->id,
                'room_id' => $contract->room_id,
                'type' => InspectionType::CheckIn,
                'inspected_on' => $data['inspected_on'],
                'inspector_id' => $this->actors->current()->id,
                'resident_acknowledged_at' => ($data['resident_acknowledged'] ?? false) ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            InspectionItems::write($inspection, $data['items']);
            $this->attachments->sync($inspection, AttachmentCollection::Inspection, $data['photos'] ?? []);

            CheckedIn::dispatch($inspection);

            return $inspection;
        });
    }
}
