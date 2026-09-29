<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\States\FiscalPeriod\Open;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\States\StateTransition;
use Illuminate\Validation\ValidationException;

/**
 * Opens a closed month again (PRD §8.11). Owner only, with a written
 * reason; the change, reason included, lands in the audit log.
 */
final class ReopenFiscalPeriod extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input  reason
     */
    public function handle(FiscalPeriod $period, array $input): FiscalPeriod
    {
        $this->authorize('reopen', $period);

        $data = $this->validate($input, [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        return $this->transaction(function () use ($period, $data): FiscalPeriod {
            $period = FiscalPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($period->isOpen()) {
                throw ValidationException::withMessages(['reason' => "Periode {$period->label()} tidak sedang ditutup."]);
            }

            $actor = $this->actors->current();
            $period->reopened_by = $actor->type === ActorType::User ? $actor->id : $actor->user?->getAuthIdentifier();
            $period->reopened_at = now();
            $period->reopen_reason = $data['reason'];
            StateTransition::to($period->status, Open::class, 'reason');

            return $period;
        });
    }
}
