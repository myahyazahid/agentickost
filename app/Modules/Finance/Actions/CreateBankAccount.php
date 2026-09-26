<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\BankAccountKind;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;

/**
 * Adds a transfer destination with its own ledger account under
 * "Bank dan e-wallet" (FR-PRP-03, FR-ACC-04).
 */
final class CreateBankAccount extends Action
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): BankAccount
    {
        $this->authorize('create', BankAccount::class);

        $data = $this->validate($input, self::rules($this->tenants->id()));

        return $this->transaction(function () use ($data): BankAccount {
            $parent = Account::query()->lockForUpdate()->findOrFail(Account::system(AccountSubtype::Bank)->id);
            $sequence = $parent->children()->count() + 1;

            $ledger = Account::create([
                'code' => sprintf('%s-%02d', $parent->code, $sequence),
                'name' => "{$data['provider_name']} {$data['account_number']}",
                'type' => AccountType::Asset,
                'subtype' => AccountSubtype::Bank,
                'parent_id' => $parent->id,
                'property_id' => $data['property_id'] ?? null,
                'is_system' => true,
            ]);

            $bankAccount = BankAccount::create([...$data, 'ledger_account_id' => $ledger->id]);

            if ($bankAccount->is_default) {
                self::clearOtherDefaults($bankAccount);
            }

            return $bankAccount;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $tenantId): array
    {
        return [
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'kind' => ['required', Rule::enum(BankAccountKind::class)],
            'provider_name' => ['required', 'string', 'max:80'],
            'account_number' => ['required', 'string', 'max:40'],
            'account_holder' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * Only one default destination per property (or for "all properties").
     */
    public static function clearOtherDefaults(BankAccount $bankAccount): void
    {
        BankAccount::query()
            ->whereKeyNot($bankAccount->id)
            ->where('is_default', true)
            ->where('property_id', $bankAccount->property_id)
            ->get()
            ->each(fn (BankAccount $other) => $other->update(['is_default' => false]));
    }
}
