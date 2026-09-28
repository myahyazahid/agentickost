<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;

/**
 * Adds an account of the tenant's own (FR-ACC-01): an expense or income
 * category, or another cash box under "Kas" such as one per property
 * (FR-ACC-04). Bank accounts are added as transfer destinations instead.
 */
final class CreateAccount extends Action
{
    public const KINDS = [
        'expense' => 'Kategori pengeluaran',
        'revenue' => 'Kategori pendapatan',
        'cash' => 'Kas',
    ];

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Account
    {
        $this->authorize('create', Account::class);

        $data = $this->validate($input, [
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9][0-9-]*$/', Rule::unique('accounts', 'code')->where('tenant_id', $this->tenants->id())],
            'name' => ['required', 'string', 'max:100'],
            'property_id' => ['nullable', Rule::exists('properties', 'id')->where('tenant_id', $this->tenants->id())->whereNull('deleted_at')],
        ]);

        return $this->transaction(fn (): Account => Account::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'property_id' => $data['property_id'] ?? null,
            ...match ($data['kind']) {
                'expense' => ['type' => AccountType::Expense],
                'revenue' => ['type' => AccountType::Revenue],
                default => [
                    'type' => AccountType::Asset,
                    'subtype' => AccountSubtype::Cash,
                    'parent_id' => Account::system(AccountSubtype::Cash)->id,
                ],
            },
        ]));
    }

    /**
     * The next free code in the group of a kind, such as 5-2000 after 5-1900.
     */
    public static function suggestCode(string $kind): string
    {
        $prefix = match ($kind) {
            'expense' => '5-',
            'revenue' => '4-',
            default => Account::system(AccountSubtype::Cash)->code.'-',
        };

        $codes = Account::query()->where('code', 'like', $prefix.'%')->pluck('code')->all();

        if ($kind === 'cash') {
            return sprintf('%s%02d', $prefix, count($codes) + 1);
        }

        $numbers = array_map(fn (string $code): int => (int) substr($code, strlen($prefix), 4), $codes);

        return $prefix.(intdiv(max([1000, ...$numbers]), 1000) * 1000 + 1000);
    }
}
