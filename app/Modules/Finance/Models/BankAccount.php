<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\BankAccountFactory;
use App\Modules\Finance\Enums\BankAccountKind;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where residents send transfers (FR-PRP-03). Every bank account has its own
 * ledger account, so transfers land on the right cash/bank balance.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $property_id
 * @property BankAccountKind $kind
 * @property string $provider_name
 * @property string $account_number
 * @property string $account_holder
 * @property string $ledger_account_id
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['property_id', 'kind', 'provider_name', 'account_number', 'account_holder', 'ledger_account_id', 'is_default', 'is_active'])]
#[UseFactory(BankAccountFactory::class)]
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    /**
     * NULL means the account is shown for every property.
     *
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ledger_account_id');
    }

    public function displayName(): string
    {
        return "{$this->provider_name} {$this->account_number} a.n. {$this->account_holder}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => BankAccountKind::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
