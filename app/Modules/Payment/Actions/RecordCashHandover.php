<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Support\StaffCashAccounts;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Records cash a staff member hands to the owner's cash or bank account
 * (FR-PAY-08). The amount they should be holding is fixed at this moment,
 * so a difference shows on the handover until the owner settles it.
 */
final class RecordCashHandover extends Action
{
    public function __construct(private readonly ActorContext $actors) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): StaffCashHandover
    {
        $data = $this->validate($input, [
            'staff_user_id' => ['nullable', 'string'],
            'actual_amount' => ['required', 'integer', 'min:1'],
            'destination_account_id' => ['required', 'string'],
            'handed_over_at' => ['required', 'date'],
        ]);

        $staff = User::query()->whereKey($data['staff_user_id'] ?? $this->actors->current()->id)->first()
            ?? throw ValidationException::withMessages(['staff_user_id' => 'Staf tidak ditemukan.']);

        $this->authorize('handOverIn', [StaffCashHandover::class, $property, $staff]);

        if (! StaffCash::tracks($staff)) {
            throw ValidationException::withMessages(['staff_user_id' => 'Uang tunai yang diterima owner langsung masuk kas, tidak perlu disetor.']);
        }

        if (CarbonImmutable::parse($data['handed_over_at'])->isFuture()) {
            throw ValidationException::withMessages(['handed_over_at' => 'Waktu setor tidak boleh di masa depan.']);
        }

        $destination = RefundDeposit::payoutAccounts()->whereKey($data['destination_account_id'])->first()
            ?? throw ValidationException::withMessages(['destination_account_id' => 'Pilih kas atau rekening tujuan setoran.']);

        return $this->transaction(function () use ($property, $staff, $destination, $data): StaffCashHandover {
            Account::query()->whereKey(StaffCashAccounts::for($staff)->id)->lockForUpdate()->firstOrFail();

            return StaffCashHandover::create([
                'property_id' => $property->id,
                'staff_user_id' => $staff->id,
                'expected_amount' => StaffCash::balance($staff->id, $property->id),
                'actual_amount' => $data['actual_amount'],
                'destination_account_id' => $destination->id,
                'handed_over_at' => $data['handed_over_at'],
            ]);
        });
    }
}
