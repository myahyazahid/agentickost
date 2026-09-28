<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Support\Expenses;
use App\Modules\Finance\Support\SpendingAccounts;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Records money spent for a property with its category and receipts
 * (FR-ACC-03). A caretaker paying from the cash they hold lowers what they
 * must hand over.
 */
final class RecordExpense extends Action
{
    public function __construct(
        private readonly AttachmentSync $attachments,
        private readonly ActorContext $actors,
        private readonly Expenses $expenses,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Property $property, array $input): Expense
    {
        $this->authorize('recordIn', [Expense::class, $property]);

        $data = $this->validate($input, [
            'expense_account_id' => ['required', 'string'],
            'paid_from_account_id' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'spent_on' => ['required', 'date'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
            'receipts' => ['nullable', 'array', 'max:5'],
            'receipts.*' => ['string'],
        ]);

        if (! SpendingAccounts::expenseAccounts()->whereKey($data['expense_account_id'])->exists()) {
            throw ValidationException::withMessages(['expense_account_id' => 'Pilih kategori pengeluaran.']);
        }

        $actor = $this->actors->current();
        $user = $actor->type === ActorType::User ? User::query()->find($actor->id) : null;

        if ($user === null || ! SpendingAccounts::paidFrom($user)->whereKey($data['paid_from_account_id'])->exists()) {
            throw ValidationException::withMessages(['paid_from_account_id' => 'Anda tidak bisa membayar dari kas atau rekening itu.']);
        }

        if (CarbonImmutable::parse($data['spent_on'])->greaterThan($property->today())) {
            throw ValidationException::withMessages(['spent_on' => 'Tanggal pengeluaran tidak boleh di masa depan.']);
        }

        return $this->transaction(function () use ($property, $data): Expense {
            $expense = $this->expenses->record(
                $property,
                $data['expense_account_id'],
                $data['paid_from_account_id'],
                (int) $data['amount'],
                CarbonImmutable::parse($data['spent_on']),
                $data['description'],
            );

            $this->attachments->sync($expense, AttachmentCollection::Document, $data['receipts'] ?? [], 'receipts');

            return $expense;
        });
    }
}
