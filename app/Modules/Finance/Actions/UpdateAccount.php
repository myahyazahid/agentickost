<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\Account;
use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;

/**
 * Renames an account, or retires one of the tenant's own so it is no longer
 * offered. System accounts stay active: automatic journals need them.
 */
final class UpdateAccount extends Action
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Account $account, array $input): Account
    {
        $this->authorize('update', $account);

        $data = $this->validate($input, [
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($account->is_system && ($data['is_active'] ?? true) === false) {
            throw ValidationException::withMessages(['is_active' => 'Akun bawaan dipakai jurnal otomatis dan tidak bisa dinonaktifkan.']);
        }

        return $this->transaction(function () use ($account, $data): Account {
            $account->update($data);

            return $account;
        });
    }
}
