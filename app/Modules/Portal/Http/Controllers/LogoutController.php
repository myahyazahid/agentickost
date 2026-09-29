<?php

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Portal\Support\PortalSession;
use Illuminate\Http\RedirectResponse;

final class LogoutController
{
    public function __invoke(PortalSession $session): RedirectResponse
    {
        $session->logout();

        return redirect()->route('portal.login')->with('portal.status', 'Anda sudah keluar.');
    }
}
