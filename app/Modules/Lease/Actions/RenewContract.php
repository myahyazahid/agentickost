<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Property\Enums\RentalPeriod;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Drafts a renewal with new terms, linked to the running contract
 * (FR-KTR-03, PRD §9.3). It starts the day after the current contract ends
 * and activates on that day; open-ended contracts get an end date here.
 */
final class RenewContract extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->status->equals(Active::class)) {
            throw ValidationException::withMessages(['status' => 'Hanya kontrak aktif yang bisa diperpanjang.']);
        }

        if ($contract->renewal()->exists()) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini sudah punya perpanjangan.']);
        }

        $data = $this->validate($input, [
            'rental_period' => ['nullable', Rule::enum(RentalPeriod::class)],
            'rent_amount' => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['nullable', 'integer', 'min:0'],
            'start_date' => [
                $contract->end_date === null ? 'required' : 'nullable',
                'date',
                'after:'.$contract->start_date->toDateString(),
            ],
            'end_date' => ['nullable', 'date'],
        ]);

        $start = $contract->end_date !== null
            ? CarbonImmutable::parse($contract->end_date)->addDay()
            : CarbonImmutable::parse($data['start_date']);

        if (isset($data['end_date']) && CarbonImmutable::parse($data['end_date'])->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah perpanjangan dimulai.']);
        }

        return $this->transaction(function () use ($contract, $data, $start): Contract {
            if ($contract->end_date === null) {
                $contract->update(['end_date' => $start->subDay()]);
            }

            $renewal = Contract::create([
                'property_id' => $contract->property_id,
                'room_id' => $contract->room_id,
                'payer_id' => $contract->payer_id,
                'rental_period' => $data['rental_period'] ?? $contract->rental_period,
                'rent_amount' => $data['rent_amount'],
                'deposit_amount' => $data['deposit_amount'] ?? $contract->deposit_amount,
                'start_date' => $start,
                'end_date' => $data['end_date'] ?? null,
                'billing_anchor_day' => $contract->billing_anchor_day,
                'next_period_start' => $start,
                'notify_resident' => $contract->notify_resident,
                'notify_payer' => $contract->notify_payer,
                'early_termination_penalty_amount' => $contract->early_termination_penalty_amount,
                'clauses' => $contract->clauses,
                'renewed_from_contract_id' => $contract->id,
                'created_by' => $this->actors->current()->type === ActorType::User ? $this->actors->current()->id : null,
            ]);

            $contract->occupants()->whereNull('left_on')->get()->each(
                fn (ContractResident $occupant) => ContractResident::create([
                    'contract_id' => $renewal->id,
                    'resident_id' => $occupant->resident_id,
                    'is_primary' => $occupant->is_primary,
                    'share_type' => $occupant->share_type,
                    'share_amount' => $occupant->share_amount,
                    'joined_on' => $start,
                ]),
            );

            return $renewal;
        });
    }
}
