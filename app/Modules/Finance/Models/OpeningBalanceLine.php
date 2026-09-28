<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\OpeningBalanceLineFactory;
use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Modules\Lease\Models\Contract;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One amount of an opening balance: the arrears, deposit held, or credit of
 * a contract, or the money on a cash or bank account on the cut-off date.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $opening_balance_id
 * @property OpeningBalanceKind $kind
 * @property string|null $contract_id
 * @property string|null $account_id
 * @property int $amount
 * @property string|null $note
 */
#[Fillable(['opening_balance_id', 'kind', 'contract_id', 'account_id', 'amount', 'note'])]
#[UseFactory(OpeningBalanceLineFactory::class)]
class OpeningBalanceLine extends Model
{
    /** @use HasFactory<OpeningBalanceLineFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            if ($line->openingBalance()->first()?->isDraft() === false) {
                throw new LogicException('Rincian saldo awal yang sudah diposting tidak dapat diubah.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<OpeningBalance, $this>
     */
    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => OpeningBalanceKind::class,
            'amount' => RupiahCast::class,
        ];
    }
}
