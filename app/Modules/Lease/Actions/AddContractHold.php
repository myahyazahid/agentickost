<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractHold;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Terminated;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * A special rate for a period away, such as a semester break (FR-KTR-05).
 * Billing periods that start inside the hold use the hold rate.
 */
final class AddContractHold extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): ContractHold
    {
        $this->authorize('update', $contract);

        if ($contract->status->equals(Completed::class, Terminated::class)) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini sudah berakhir.']);
        }

        $endRules = ['required', 'date', 'after_or_equal:start_date'];

        if ($contract->end_date !== null) {
            $endRules[] = 'before_or_equal:'.$contract->end_date->toDateString();
        }

        $data = $this->validate($input, [
            'start_date' => ['required', 'date', 'after_or_equal:'.$contract->start_date->toDateString()],
            'end_date' => $endRules,
            'rent_amount' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $overlaps = $contract->holds()
            ->whereDate('start_date', '<=', CarbonImmutable::parse($data['end_date']))
            ->whereDate('end_date', '>=', CarbonImmutable::parse($data['start_date']))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'Tanggal ini bertumpuk dengan masa hold lain.']);
        }

        $actor = $this->actors->current();

        return $this->transaction(fn (): ContractHold => $contract->holds()->create([
            ...$data,
            'created_by' => $actor->type === ActorType::User ? $actor->id : null,
        ]));
    }
}
