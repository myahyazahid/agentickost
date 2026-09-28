<?php

namespace App\Modules\Payment\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where the payment record came from.
 */
enum PaymentChannel: string implements HasLabel
{
    case Manual = 'manual';
    case Portal = 'portal';
    case Gateway = 'gateway';
    case Agent = 'agent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Dicatat staf',
            self::Portal => 'Portal penghuni',
            self::Gateway => 'Payment gateway',
            self::Agent => 'Agent',
        };
    }
}
