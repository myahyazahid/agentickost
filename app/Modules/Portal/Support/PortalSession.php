<?php

namespace App\Modules\Portal\Support;

use App\Modules\Lease\Models\Payer;
use App\Modules\Lease\Models\Resident;
use Illuminate\Support\Facades\Auth;

/**
 * Who is logged in to the portal: a resident on the `resident` guard, or a
 * payer who lives elsewhere on the `payer` guard (FR-PRT-01, FR-PRT-07).
 * Both stay logged in on the phone until they log out.
 */
final class PortalSession
{
    public function account(): Resident|Payer|null
    {
        $resident = Auth::guard('resident')->user();

        if ($resident instanceof Resident) {
            return $resident;
        }

        $payer = Auth::guard('payer')->user();

        return $payer instanceof Payer ? $payer : null;
    }

    public function login(Resident|Payer $account): void
    {
        $this->logout();

        Auth::guard($account instanceof Resident ? 'resident' : 'payer')->login($account, remember: true);

        session()->regenerate();
    }

    public function logout(): void
    {
        foreach (['resident', 'payer'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        session()->regenerateToken();
    }
}
