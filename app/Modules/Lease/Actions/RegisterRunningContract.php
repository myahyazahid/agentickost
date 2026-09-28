<?php

namespace App\Modules\Lease\Actions;

use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Support\BillingCursor;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Carries in a contract that was already running before the tenant started
 * using KostPilot (FR-ONB-02). It is created and activated like any other
 * contract, but billing starts at the first period the owner has not billed
 * yet, and its deposit is never billed: the deposit held comes in with the
 * opening balance (FR-ONB-04).
 */
final class RegisterRunningContract extends Action
{
    public function __construct(
        private readonly CreateContract $create,
        private readonly ActivateContract $activate,
        private readonly BillingCursor $cursor,
    ) {}

    /**
     * @param  array<string, mixed>  $input  CreateContract input plus billing_starts_on
     */
    public function handle(array $input): Contract
    {
        $this->authorize('create', Contract::class);

        $data = $this->validate($input, [
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'billing_starts_on' => ['required', 'date'],
        ]);

        $start = CarbonImmutable::parse($data['start_date']);
        $billingStart = CarbonImmutable::parse($data['billing_starts_on']);

        if ($billingStart->lessThan($start)) {
            throw ValidationException::withMessages([
                'billing_starts_on' => 'Tagihan berikutnya tidak boleh sebelum tanggal mulai kontrak.',
            ]);
        }

        if (($data['end_date'] ?? null) !== null && $billingStart->greaterThan(CarbonImmutable::parse($data['end_date']))) {
            throw ValidationException::withMessages([
                'billing_starts_on' => 'Tagihan berikutnya tidak boleh sesudah tanggal selesai kontrak.',
            ]);
        }

        return $this->transaction(function () use ($input, $billingStart): Contract {
            $contract = $this->create->handle($input);
            $contract->imported_at = now();
            $this->cursor->moveTo($contract, $billingStart);

            return $this->activate->handle($contract);
        });
    }
}
