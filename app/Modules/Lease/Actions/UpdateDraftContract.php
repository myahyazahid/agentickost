<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Property\Enums\BillingMode;
use App\Modules\Property\Enums\RentalPeriod;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Changes the terms of a contract that has not started. Once active, the
 * terms are locked; changes go through a renewal.
 */
final class UpdateDraftContract extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Contract
    {
        $this->authorize('update', $contract);

        if (! $contract->status->equals(Draft::class)) {
            throw ValidationException::withMessages(['status' => 'Syarat kontrak yang sudah aktif tidak bisa diubah. Buat perpanjangan dengan syarat baru.']);
        }

        $rules = CreateContract::rules($contract->tenant_id);

        $data = $this->validate($input, array_intersect_key($rules, array_flip([
            'rental_period', 'rent_amount', 'deposit_amount', 'start_date', 'end_date',
            'early_termination_penalty_amount', 'clauses',
        ])));

        $period = RentalPeriod::from($data['rental_period']);
        $start = CarbonImmutable::parse($data['start_date']);
        $settings = $contract->property()->firstOrFail()->resolvedSettings();

        return $this->transaction(function () use ($contract, $data, $period, $start, $settings): Contract {
            $contract->update([
                ...$data,
                'billing_anchor_day' => $settings->billing_mode === BillingMode::FixedDate && $period->isMonthBased()
                    ? (int) $settings->fixed_billing_day
                    : $start->day,
                'next_period_start' => $start,
            ]);

            $contract->occupants()->get()->each(fn (ContractResident $occupant) => $occupant->update(['joined_on' => $start]));

            return $contract;
        });
    }
}
