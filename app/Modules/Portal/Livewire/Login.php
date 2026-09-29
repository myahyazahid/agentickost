<?php

namespace App\Modules\Portal\Livewire;

use App\Modules\Lease\Support\PortalAccess;
use App\Modules\Portal\Actions\RequestPortalCode;
use App\Modules\Portal\Actions\VerifyPortalCode;
use App\Modules\Portal\Support\PortalSession;
use App\Modules\Tenancy\TenantContext;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Portal login (FR-PRT-01): the phone number, then the code sent to it by
 * WhatsApp. With AGENTICKOST_PORTAL_OTP_REQUIRED=false (never in
 * production) the number alone logs in, for local development.
 */
class Login extends Component
{
    public string $phone = '';

    public string $code = '';

    #[Locked]
    public ?string $sentTo = null;

    public function mount(PortalSession $session): void
    {
        $account = $session->account();

        if ($account !== null && PortalAccess::for($account)->contractIds() !== []) {
            $this->redirectRoute('portal.home');
        }
    }

    public function sendCode(RequestPortalCode $request, PortalSession $session): void
    {
        $this->resetErrorBag();

        if (! config('agentickost.portal_otp_required')) {
            $this->logInWithoutCode($session);

            return;
        }

        if (! self::canDeliverCodes()) {
            $this->addError('phone', 'Pengiriman kode WhatsApp belum diatur. Hubungi pengelola kost.');

            return;
        }

        $this->sentTo = $request->handle(['phone' => $this->phone], request()->ip());
        $this->code = '';
    }

    public function verify(VerifyPortalCode $verify, PortalSession $session): void
    {
        if ($this->sentTo === null) {
            return;
        }

        $session->login($verify->handle(['phone' => $this->sentTo, 'code' => $this->code]));

        $this->redirectRoute('portal.home');
    }

    public function useAnotherNumber(): void
    {
        $this->reset('sentTo', 'code');
        $this->resetErrorBag();
    }

    /**
     * In production codes need a real WhatsApp driver; "log" would only
     * write them to the server log.
     */
    public static function canDeliverCodes(): bool
    {
        return ! app()->isProduction() || config('agentickost.whatsapp.driver') !== 'log';
    }

    private function logInWithoutCode(PortalSession $session): void
    {
        $phone = Phone::normalize($this->phone);
        $account = Phone::isValid($phone) ? PortalAccess::findAccount((string) $phone) : null;

        if ($account === null) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini tidak terdaftar sebagai penghuni atau pembayar di kost ini.']);
        }

        $session->login($account);

        $this->redirectRoute('portal.home');
    }

    public function render(): View
    {
        $tenant = app(TenantContext::class)->tenant();

        return view('portal::livewire.login', [
            'portalTenant' => $tenant,
            'otpRequired' => (bool) config('agentickost.portal_otp_required'),
            'status' => session('portal.status'),
        ])->layout('portal::layout', ['title' => 'Masuk', 'portalTenant' => $tenant]);
    }
}
