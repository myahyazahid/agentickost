<?php

namespace App\Modules\Portal\Actions;

use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Portal\Enums\OtpPurpose;
use App\Modules\Portal\Models\OtpCode;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Messaging\MessageChannel;
use App\Support\Messaging\MessageNotSent;
use App\Support\Phone;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Sends a portal login code to a phone by WhatsApp (FR-PRT-01). A number
 * that is not a resident or payer gets nothing, and the answer looks the
 * same, so the form cannot be used to find out who lives where. Requests
 * are limited per number and per address (NFR-SEC-03). Works in read-only
 * mode: residents may still look at their bills.
 */
final class RequestPortalCode extends Action implements AllowedWhenReadOnly
{
    public const CODE_MINUTES = 5;

    public const REQUESTS_PER_PHONE = 3;

    public const REQUESTS_PER_ADDRESS = 10;

    public const WINDOW_SECONDS = 900;

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly MessageChannel $messages,
    ) {}

    /**
     * @param  array<string, mixed>  $input  phone
     * @return string the phone in E.164, to carry to the code step
     */
    public function handle(array $input, ?string $ipAddress = null): string
    {
        $phone = Phone::normalize(is_string($input['phone'] ?? null) ? $input['phone'] : null);

        if (! Phone::isValid($phone)) {
            throw ValidationException::withMessages(['phone' => 'Nomor HP tidak valid. Contoh: 0812 3456 7890.']);
        }

        $tenant = $this->tenants->tenant();
        $this->throttle('portal-code:'.$tenant->id.':'.hash('sha256', (string) $phone), self::REQUESTS_PER_PHONE);

        if ($ipAddress !== null) {
            $this->throttle('portal-code-address:'.hash('sha256', $ipAddress), self::REQUESTS_PER_ADDRESS);
        }

        if (PortalAccess::findAccount((string) $phone) === null) {
            return (string) $phone;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $this->transaction(function () use ($phone, $code): void {
            OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', OtpPurpose::PortalLogin->value)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            OtpCode::create([
                'phone' => $phone,
                'purpose' => OtpPurpose::PortalLogin,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_MINUTES),
            ]);
        });

        try {
            $this->messages->send((string) $phone, "Kode masuk portal {$tenant->name}: {$code}. Berlaku ".self::CODE_MINUTES.' menit. Jangan berikan kode ini kepada siapa pun, termasuk pengelola kost.');
        } catch (MessageNotSent $exception) {
            report($exception);

            throw ValidationException::withMessages(['phone' => 'Kode belum bisa dikirim. Coba lagi beberapa menit lagi.']);
        }

        return (string) $phone;
    }

    private function throttle(string $key, int $maxAttempts): void
    {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'phone' => 'Terlalu sering meminta kode. Coba lagi dalam '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' menit.',
            ]);
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);
    }
}
