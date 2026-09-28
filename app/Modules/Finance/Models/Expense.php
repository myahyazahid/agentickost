<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Finance\Database\Factories\ExpenseFactory;
use App\Modules\Property\Concerns\BelongsToProperty;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Money spent for a property, such as electricity or repairs (FR-ACC-03).
 * A financial document: only voided with a reason, never edited or deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $expense_account_id
 * @property string $paid_from_account_id
 * @property string|null $ticket_id Set when a maintenance ticket caused the expense (FR-MNT-04)
 * @property int $amount
 * @property Carbon $spent_on
 * @property string $description
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $created_by
 */
#[Fillable(['property_id', 'expense_account_id', 'paid_from_account_id', 'ticket_id', 'amount', 'spent_on', 'description', 'created_by'])]
#[UseFactory(ExpenseFactory::class)]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use Auditable, BelongsToProperty, BelongsToTenant, HasAttachments, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (self $expense): void {
            $changed = array_diff(array_keys($expense->getDirty()), ['voided_at', 'void_reason', 'updated_at']);

            if ($changed !== [] || $expense->getRawOriginal('voided_at') !== null) {
                throw new LogicException('Pengeluaran tidak dapat diubah. Batalkan lalu catat ulang.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Pengeluaran tidak dapat dihapus. Batalkan dengan alasan.'));
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount' => RupiahCast::class,
            'spent_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }
}
