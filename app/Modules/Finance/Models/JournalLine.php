<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\JournalLineFactory;
use App\Modules\Lease\Models\Contract;
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
 * One debit or credit of a journal entry. Lines carry the contract for the
 * receivable, deposit, and credit sub-ledgers.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $journal_entry_id
 * @property string $account_id
 * @property string|null $property_id
 * @property string|null $contract_id
 * @property int $debit_amount
 * @property int $credit_amount
 * @property string|null $memo
 * @property Carbon $created_at
 */
#[Fillable(['journal_entry_id', 'account_id', 'property_id', 'contract_id', 'debit_amount', 'credit_amount', 'memo'])]
#[UseFactory(JournalLineFactory::class)]
class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'debit_amount' => 0,
        'credit_amount' => 0,
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Baris jurnal tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Baris jurnal tidak dapat dihapus.'));
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'debit_amount' => RupiahCast::class,
            'credit_amount' => RupiahCast::class,
        ];
    }
}
