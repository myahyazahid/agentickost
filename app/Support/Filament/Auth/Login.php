<?php

namespace App\Support\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Filament's login, which limits attempts per IP address, plus a limit per
 * account (NFR-SEC-03): guessing one account's password from many
 * addresses locks that account's login for a while.
 */
class Login extends BaseLogin
{
    public const ATTEMPTS_PER_ACCOUNT = 10;

    public const LOCKOUT_SECONDS = 900;

    public function authenticate(): ?LoginResponse
    {
        $key = $this->accountThrottleKey();

        if ($key !== null && RateLimiter::tooManyAttempts($key, self::ATTEMPTS_PER_ACCOUNT)) {
            Notification::make()
                ->danger()
                ->title('Terlalu banyak percobaan masuk')
                ->body('Login akun ini dikunci sementara. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' menit, atau atur ulang password.')
                ->send();

            return null;
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $exception) {
            if ($key !== null) {
                RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            }

            throw $exception;
        }

        if ($response !== null && $key !== null) {
            RateLimiter::clear($key);
        }

        return $response;
    }

    private function accountThrottleKey(): ?string
    {
        $email = $this->data['email'] ?? null;

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return 'login-account:'.Filament::getCurrentPanel()?->getId().':'.hash('sha256', mb_strtolower(trim($email)));
    }
}
