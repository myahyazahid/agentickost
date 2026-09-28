<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\Inspection;
use App\Modules\Lease\Models\Settlement;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Lease\Support\InspectionItems;
use App\Modules\Lease\Support\SettlementCalculator;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Checks the resident out (FR-SIK-04): the room is inspected, damage is
 * listed with its cost, and a draft settlement shows what is still owed or
 * due back (FR-SIK-05). Nothing is billed or paid until the owner finalizes
 * the settlement.
 *
 * A contract checks out after notice, after termination, or on reaching its
 * end date. Leaving without notice means giving notice or terminating first.
 */
final class RecordCheckOut extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
        private readonly SettlementCalculator $calculator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): Settlement
    {
        $this->authorize('inspect', $contract);

        $today = $contract->property()->firstOrFail()->today();

        $data = $this->validate($input, [
            'moved_out_on' => ['required', 'date', 'after_or_equal:'.$contract->start_date->toDateString(), 'before_or_equal:'.$today->toDateString()],
            'room_after' => ['required', Rule::enum(RoomAfterCheckOut::class)],
            'early_termination_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['string'],
            ...InspectionItems::rules(withCharges: true),
        ]);

        $this->ensureCanCheckOut($contract, $today);

        return $this->transaction(function () use ($contract, $data, $today): Settlement {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $inspection = Inspection::create([
                'contract_id' => $contract->id,
                'room_id' => $contract->room_id,
                'type' => InspectionType::CheckOut,
                'inspected_on' => $data['moved_out_on'],
                'inspector_id' => $this->actors->current()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            InspectionItems::write($inspection, $data['items']);
            $this->attachments->sync($inspection, AttachmentCollection::Inspection, $data['photos'] ?? []);

            $damage = $inspection->chargedAmount();
            $penalty = (int) ($data['early_termination_amount'] ?? $this->calculator->proposedPenalty($contract));
            $balances = $this->calculator->balances($contract, $today);

            return Settlement::create([
                'contract_id' => $contract->id,
                'inspection_id' => $inspection->id,
                'moved_out_on' => $data['moved_out_on'],
                'room_after' => $data['room_after'],
                'outstanding_amount' => max(0, $balances['outstanding']),
                'damage_amount' => $damage,
                'early_termination_amount' => $penalty,
                'deposit_balance_amount' => $balances['deposit'],
                'credit_balance_amount' => $balances['credit'],
                'result_amount' => SettlementCalculator::result(max(0, $balances['outstanding']), $damage, $penalty, $balances['deposit'], $balances['credit']),
            ]);
        });
    }

    private function ensureCanCheckOut(Contract $contract, CarbonImmutable $today): void
    {
        if ($contract->settlement()->exists()) {
            throw ValidationException::withMessages(['moved_out_on' => 'Kontrak ini sudah check-out.']);
        }

        $endedNaturally = $contract->status->equals(Active::class)
            && $contract->end_date !== null
            && ! $contract->end_date->greaterThan($today);

        if (! $contract->status->equals(Notice::class, Terminated::class) && ! $endedNaturally) {
            throw ValidationException::withMessages([
                'moved_out_on' => 'Catat rencana keluar atau putus kontrak lebih dulu, supaya tanggal akhir sewa jelas.',
            ]);
        }
    }
}
