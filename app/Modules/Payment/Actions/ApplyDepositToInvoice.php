<?php

namespace App\Modules\Payment\Actions;

use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Events\DepositDeducted;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\PaymentAllocator;
use App\Modules\Payment\Support\AllocationWriter;
use App\Modules\Payment\Support\OpenInvoices;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Actions\Action;
use App\Support\Money\Rupiah;
use Illuminate\Validation\ValidationException;

/**
 * Pays an invoice from the contract's deposit, with the owner's approval
 * (PRD §8.6). The deposit is not used for its own deposit component.
 */
final class ApplyDepositToInvoice extends Action
{
    public function __construct(
        private readonly DepositLedger $deposits,
        private readonly AllocationWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Invoice $invoice, array $input): DepositTransaction
    {
        $contract = $invoice->contract()->first() ?? throw ValidationException::withMessages(['amount' => 'Tagihan ini tidak terikat kontrak.']);

        $this->authorize('manageFor', [DepositTransaction::class, $contract]);

        $data = $this->validate($input, [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->transaction(function () use ($invoice, $contract, $data): DepositTransaction {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();
            $invoices = OpenInvoices::lockForContract($contract->id);

            if (! isset($invoices[$invoice->id])) {
                throw ValidationException::withMessages(['amount' => 'Tagihan ini tidak sedang menunggu pembayaran.']);
            }

            $plan = PaymentAllocator::allocate(
                $data['amount'],
                OpenInvoices::describe([$invoice->id => $invoices[$invoice->id]]),
                OpenInvoices::order($contract),
                except: [AllocationCategory::Deposit],
            );

            if ($plan->remainder > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Yang bisa dibayar dari deposit untuk tagihan ini paling banyak '.Rupiah::format($plan->allocated()).'.',
                ]);
            }

            $entry = $this->deposits->record($contract, DepositTransactionType::Deducted, -(int) $data['amount'], [
                'invoice_id' => $invoice->id,
                'reason' => $data['reason'],
            ]);

            $this->writer->write($contract, $plan->lines, $invoices, ['deposit_transaction_id' => $entry->id]);

            DepositDeducted::dispatch($entry);

            return $entry;
        });
    }
}
