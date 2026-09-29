<?php

namespace App\Modules\Portal\Actions;

use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Portal\Enums\OtpPurpose;
use App\Modules\Portal\Models\OtpCode;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Phone;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Checks a portal login code and returns who it logs in as (FR-PRT-01).
 * A code works once, for CODE_MINUTES, and stops working after
 * MAX_ATTEMPTS wrong guesses (NFR-SEC-03). Logging in is left to the
 * caller.
 */
final class VerifyPortalCode extends Action implements AllowedWhenReadOnly
{
    public const MAX_ATTEMPTS = 5;

    public const CHECKS_PER_PHONE = 10;

    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $input  phone, code
     */
    public function handle(array $input): Resident|Payer
    {
        $phone = Phone::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null);
        $code = preg_replace('/\D/', '', is_string($input['code'] ?? null) ? $input['code'] : '') ?? '';

        if (! Phone::isValid($phone) || strlen($code) !== 6) {
            throw ValidationException::withMessages(['code' => 'Masukkan 6 angka kode dari WhatsApp.']);
        }

        $key = 'portal-code-check:'.$this->tenants->id().':'.hash('sha256', (string) $phone);

        if (RateLimiter::tooManyAttempts($key, self::CHECKS_PER_PHONE)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' menit.']);
        }

        RateLimiter::hit($key, RequestPortalCode::WINDOW_SECONDS);

        $matched = $this->transaction(function () use ($phone, $code): bool {
            $otp = OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', OtpPurpose::PortalLogin->value)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->attempts >= self::MAX_ATTEMPTS) {
                return false;
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->attempts++;
                $otp->save();

                return false;
            }

            $otp->consumed_at = now();
            $otp->save();

            return true;
        });

        if (! $matched) {
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah tidak berlaku. Periksa lagi, atau minta kode baru.']);
        }

        RateLimiter::clear($key);

        return PortalAccess::findAccount((string) $phone)
            ?? throw ValidationException::withMessages(['code' => 'Nomor ini tidak lagi terdaftar di kontrak mana pun. Hubungi pengelola kost.']);
    }
}
