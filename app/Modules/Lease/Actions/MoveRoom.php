<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Billing\Support\RoomMoveBilling;
use App\Modules\Lease\Events\RoomMoved;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\RoomMove;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Lease\Support\BillingCursor;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\Support\RoomOccupancy;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Moves a running contract to another room of the same property in the
 * middle of a period (FR-SIK-02, PRD §8.8). The old room is charged up to
 * the day before the move and the rest of its paid rent becomes credit; the
 * new room is billed from the move date at the price in force then, unless
 * another rent is agreed. A higher deposit for the new room is billed; a
 * lower one stays held until check-out or a refund.
 */
final class MoveRoom extends Action
{
    public function __construct(
        private readonly RoomOccupancy $rooms,
        private readonly RoomPricing $pricing,
        private readonly RoomMoveBilling $billing,
        private readonly BillingCursor $cursor,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): RoomMove
    {
        $this->authorize('update', $contract);

        $property = $contract->property()->firstOrFail();
        $today = $property->today();

        $data = $this->validate($input, [
            'to_room_id' => ['required', 'string'],
            'moved_on' => ['required', 'date', 'after:'.$contract->start_date->toDateString(), 'before_or_equal:'.$today->toDateString()],
            'new_rent_amount' => ['nullable', 'integer', 'min:1'],
            'new_deposit_amount' => ['nullable', 'integer', 'min:0'],
            'old_room_needs_maintenance' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $contract->status->equals(Active::class)) {
            throw ValidationException::withMessages(['to_room_id' => 'Hanya kontrak aktif yang bisa pindah kamar.']);
        }

        if ($contract->renewal()->exists()) {
            throw ValidationException::withMessages(['to_room_id' => 'Kontrak ini punya draf perpanjangan untuk kamar lama. Hapus draf itu lebih dulu.']);
        }

        $movedOn = CarbonImmutable::parse($data['moved_on']);
        $lastDay = $this->cursor->lastBillableDay($contract);

        if ($lastDay !== null && $movedOn->greaterThan($lastDay)) {
            throw ValidationException::withMessages(['moved_on' => 'Tanggal pindah setelah kontrak berakhir.']);
        }

        $lastMove = $contract->roomMoves()->max('moved_on');

        if (is_string($lastMove) && $movedOn->lessThanOrEqualTo(CarbonImmutable::parse($lastMove))) {
            throw ValidationException::withMessages(['moved_on' => 'Tanggal pindah harus setelah pindah kamar sebelumnya.']);
        }

        $newRoom = Room::query()->where('property_id', $property->id)->whereKey($data['to_room_id'])->first();

        if ($newRoom === null || $newRoom->id === $contract->room_id) {
            throw ValidationException::withMessages(['to_room_id' => 'Pilih kamar lain di properti yang sama.']);
        }

        $newRent = $data['new_rent_amount'] ?? $this->pricing->priceFor($newRoom, $contract->rental_period, $movedOn)
            ?? throw ValidationException::withMessages(['new_rent_amount' => "Kamar {$newRoom->number} belum punya harga untuk periode ini. Isi sewanya."]);
        $newDeposit = (int) ($data['new_deposit_amount'] ?? $contract->deposit_amount);

        return $this->transaction(function () use ($contract, $newRoom, $movedOn, $today, $data, $newRent, $newDeposit): RoomMove {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            [$oldRoom, $targetRoom] = $this->lockRooms($contract->room()->firstOrFail(), $newRoom);

            $this->ensureFree($contract, $targetRoom);

            $actor = $this->actors->current();

            $move = RoomMove::create([
                'contract_id' => $contract->id,
                'from_room_id' => $oldRoom->id,
                'to_room_id' => $targetRoom->id,
                'moved_on' => $movedOn,
                'old_rent_amount' => $contract->rent_amount,
                'new_rent_amount' => $newRent,
                'deposit_difference_amount' => $newDeposit - $contract->deposit_amount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ]);

            $invoice = $this->billing->bill($contract, $move, $targetRoom, max(0, $newDeposit - $contract->deposit_amount), $today);
            $move->invoice_id = $invoice?->id;
            $move->save();

            $contract->room_id = $targetRoom->id;
            $contract->rent_amount = $newRent;
            $contract->deposit_amount = $newDeposit;
            $contract->save();

            $this->rooms->release($oldRoom, (bool) ($data['old_room_needs_maintenance'] ?? false));
            $this->rooms->occupy($targetRoom);

            RoomMoved::dispatch($move);

            return $move;
        });
    }

    /**
     * Locks both rooms in id order so two moves cannot deadlock.
     *
     * @return array{0: Room, 1: Room}
     */
    private function lockRooms(Room $old, Room $new): array
    {
        if (strcmp($old->id, $new->id) < 0) {
            return [$this->rooms->lock($old), $this->rooms->lock($new)];
        }

        $new = $this->rooms->lock($new);

        return [$this->rooms->lock($old), $new];
    }

    private function ensureFree(Contract $contract, Room $room): void
    {
        $taken = Contract::query()
            ->where('room_id', $room->id)
            ->whereIn('status', ContractState::runningValues())
            ->exists();

        if ($taken || ! $room->status->equals(Available::class)) {
            throw ValidationException::withMessages(['to_room_id' => "Kamar {$room->number} tidak tersedia."]);
        }

        $occupants = $contract->occupants()->whereNull('left_on')->count();

        if ($occupants > $room->capacity) {
            throw ValidationException::withMessages(['to_room_id' => "Kamar {$room->number} hanya untuk {$room->capacity} orang."]);
        }
    }
}
