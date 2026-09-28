<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Payment\Events\CashHandoverConfirmed;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\States\Handover\Confirmed;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Money\Rupiah;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * The owner confirms the cash arrived, entering the amount actually counted
 * if it differs from what the staff member reported. A difference against
 * the expected amount needs an explanation (PRD §8.12).
 */
final class ConfirmCashHandover extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(StaffCashHandover $handover, array $input = []): StaffCashHandover
    {
        $this->authorize('confirm', $handover);

        $data = $this->validate($input, [
            'actual_amount' => ['nullable', 'integer', 'min:0'],
            'difference_note' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->transaction(function () use ($handover, $data): StaffCashHandover {
            $handover = StaffCashHandover::query()->whereKey($handover->id)->lockForUpdate()->firstOrFail();

            if ($handover->status->equals(Confirmed::class)) {
                throw ValidationException::withMessages(['actual_amount' => 'Setoran ini sudah diterima.']);
            }

            if (($data['actual_amount'] ?? null) !== null) {
                $handover->actual_amount = (int) $data['actual_amount'];
            }

            $note = trim((string) ($data['difference_note'] ?? ''));

            if ($handover->hasDifference() && mb_strlen($note) < 5) {
                $difference = $handover->actual_amount - $handover->expected_amount;

                throw ValidationException::withMessages([
                    'difference_note' => ($difference < 0 ? 'Kurang ' : 'Lebih ').Rupiah::format(abs($difference)).' dari saldo kas staf. Tulis penjelasannya sebelum menerima setoran.',
                ]);
            }

            $actor = $this->actors->current();

            $handover->difference_note = $note !== '' ? $note : null;
            $handover->confirmed_by = $actor->id;
            $handover->confirmed_at = now();
            StateTransition::to($handover->status, Confirmed::class, 'actual_amount');

            CashHandoverConfirmed::dispatch($handover);

            return $handover;
        });
    }
}
