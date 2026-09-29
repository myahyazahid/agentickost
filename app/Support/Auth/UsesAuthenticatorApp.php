<?php

namespace App\Support\Auth;

use Illuminate\Support\Carbon;
use SensitiveParameter;

/**
 * Stores the authenticator-app secret and recovery codes for Filament's app
 * authentication (FR-USR-05, NFR-SEC-05). The model casts both columns as
 * encrypted and hides them.
 *
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 */
trait UsesAuthenticatorApp
{
    public function getAppAuthenticationSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->two_factor_secret = $secret;
        $this->two_factor_confirmed_at = $secret === null ? null : now();
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->two_factor_recovery_codes;
    }

    /**
     * @param  array<string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->two_factor_recovery_codes = $codes === null ? null : array_values($codes);
        $this->save();
    }
}
