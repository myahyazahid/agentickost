<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Terminated;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Another person moves into the room under the same contract (FR-KTR-02).
 */
final class AddResidentToContract extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): ContractResident
    {
        $this->authorize('update', $contract);

        if ($contract->status->equals(Completed::class, Terminated::class)) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini sudah berakhir.']);
        }

        $data = $this->validate($input, [
            'resident_id' => ['required', Rule::exists('residents', 'id')->where('tenant_id', $contract->tenant_id)->whereNull('deleted_at')],
            'joined_on' => ['required', 'date', 'after_or_equal:'.$contract->start_date->toDateString()],
        ]);

        $resident = Resident::query()->whereKey($data['resident_id'])->firstOrFail();
        $room = $contract->room()->firstOrFail();
        $current = $contract->occupants()->whereNull('left_on')->count();

        if ($contract->occupants()->where('resident_id', $resident->id)->exists()) {
            throw ValidationException::withMessages(['resident_id' => "{$resident->full_name} sudah tercatat di kontrak ini."]);
        }

        if ($current + 1 > $room->capacity) {
            throw ValidationException::withMessages(['resident_id' => "Kamar {$room->number} hanya untuk {$room->capacity} orang."]);
        }

        CreateContract::ensureNotLivingElsewhere(collect([$resident]));
        CreateContract::ensureGenderPolicy($contract->property()->firstOrFail()->gender_policy, collect([$resident]));

        return $this->transaction(fn (): ContractResident => ContractResident::create([
            'contract_id' => $contract->id,
            'resident_id' => $resident->id,
            'is_primary' => $current === 0,
            'joined_on' => $data['joined_on'],
        ]));
    }
}
