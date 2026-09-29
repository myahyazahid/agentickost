<?php

namespace App\Modules\Tenancy\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a tenant stands, read from its trial end and freeze date. The full
 * subscription states (PRD §9.6) come with paid plans in M1.5.1.
 */
enum TenantStatus: string implements HasColor, HasLabel
{
    case Trial = 'trial';
    case TrialEnded = 'trial_ended';
    case Active = 'active';
    case Frozen = 'frozen';

    public function getLabel(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::TrialEnded => 'Trial berakhir',
            self::Active => 'Aktif',
            self::Frozen => 'Dibekukan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::TrialEnded => 'warning',
            self::Active => 'success',
            self::Frozen => 'danger',
        };
    }
}
