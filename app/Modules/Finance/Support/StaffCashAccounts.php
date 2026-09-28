<?php

namespace App\Modules\Finance\Support;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\Account;

/**
 * The "Kas di tangan" ledger account of one staff member, under the tenant's
 * staff cash account (PRD §8.12). Created the first time the staff member
 * receives cash.
 */
final class StaffCashAccounts
{
    public static function for(User $user): Account
    {
        $existing = Account::query()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $parent = Account::query()->lockForUpdate()->findOrFail(Account::system(AccountSubtype::StaffCash)->id);

        return Account::create([
            'code' => sprintf('%s-%02d', $parent->code, $parent->children()->count() + 1),
            'name' => "Kas di tangan {$user->name}",
            'type' => AccountType::Asset,
            'subtype' => AccountSubtype::StaffCash,
            'parent_id' => $parent->id,
            'user_id' => $user->id,
            'is_system' => true,
        ]);
    }
}
