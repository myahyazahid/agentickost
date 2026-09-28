<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Finance\Database\Factories\OpeningBalanceFactory;
use App\Modules\Finance\States\OpeningBalance\Draft;
use App\Modules\Finance\States\OpeningBalance\OpeningBalanceState;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\States\EnforcesStateTransitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Spatie\ModelStates\HasStates;

/**
 * Balances carried in from the owner's books on one cut-off date
 * (FR-ONB-04): arrears, deposits held, and credit per contract, and cash per
 * account. Posting writes them into the ledgers with an opening journal
 * (FR-ONB-05); a posted opening balance never changes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property Carbon $cutoff_date
 * @property OpeningBalanceState $status
 * @property string|null $journal_entry_id
 * @property string|null $posted_by
 * @property Carbon|null $posted_at
 * @property Carbon $created_at
 */
#[Fillable(['cutoff_date'])]
#[UseFactory(OpeningBalanceFactory::class)]
class OpeningBalance extends Model
{
    /** @use HasFactory<OpeningBalanceFactory> */
    use Auditable, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (self $balance): void {
            if ($balance->getRawOriginal('status') !== Draft::$name) {
                throw new LogicException('Saldo awal yang sudah diposting tidak dapat diubah.');
            }
        });

        static::deleting(function (self $balance): void {
            if (! $balance->isDraft()) {
                throw new LogicException('Saldo awal yang sudah diposting tidak dapat dihapus.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status->equals(Draft::class);
    }

    /**
     * @return HasMany<OpeningBalanceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OpeningBalanceLine::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'status' => OpeningBalanceState::class,
            'posted_at' => 'datetime',
        ];
    }
}
