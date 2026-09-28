<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositDeducted;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Support\Actions\Action;

/**
 * Keeps part of a deposit as income, such as for damage, with a reason and
 * optional photos (FR-DEP-02, PRD §8.6). To pay an invoice from the deposit,
 * use ApplyDepositToInvoice.
 */
final class DeductDeposit extends Action
{
    public function __construct(
        private readonly DepositLedger $deposits,
        private readonly AttachmentSync $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Contract $contract, array $input): DepositTransaction
    {
        $this->authorize('manageFor', [DepositTransaction::class, $contract]);

        $data = $this->validate($input, [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['string'],
        ]);

        return $this->transaction(function () use ($contract, $data): DepositTransaction {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            $entry = $this->deposits->record($contract, DepositTransactionType::Deducted, -(int) $data['amount'], [
                'reason' => $data['reason'],
            ]);

            $this->attachments->sync($entry, AttachmentCollection::Photo, $data['photos'] ?? []);

            DepositDeducted::dispatch($entry);

            return $entry;
        });
    }
}
