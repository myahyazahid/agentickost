<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Handover\Disputed;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;

/**
 * The owner marks a handover as not matching, for example when less cash
 * arrived than reported. It stays open until confirmed with an explanation.
 */
final class DisputeCashHandover extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(StaffCashHandover $handover, array $input): StaffCashHandover
    {
        $this->authorize('confirm', $handover);

        $data = $this->validate($input, [
            'dispute_note' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($handover, $data): StaffCashHandover {
            $handover = StaffCashHandover::query()->whereKey($handover->id)->lockForUpdate()->firstOrFail();

            $handover->dispute_note = $data['dispute_note'];
            StateTransition::to($handover->status, Disputed::class, 'dispute_note');

            return $handover;
        });
    }
}
