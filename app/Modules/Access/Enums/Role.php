<?php

namespace App\Modules\Access\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Built-in roles (FR-USR-01). Role records are created per tenant.
 */
enum Role: string implements HasLabel
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Caretaker = 'caretaker';
    case Accountant = 'accountant';

    /** For the resident portal (M1.5.3); residents are not rows in `users`. */
    case Resident = 'resident';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manajer',
            self::Caretaker => 'Penjaga',
            self::Accountant => 'Akuntan',
            self::Resident => 'Penghuni',
        };
    }

    /**
     * Roles held by staff in the `app` panel.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Owner, self::Manager, self::Caretaker, self::Accountant];
    }

    /**
     * Whether the role sees every property of the tenant. The others only see
     * the properties they are assigned to (FR-USR-02).
     */
    public function seesAllProperties(): bool
    {
        return in_array($this, [self::Owner, self::Accountant], true);
    }
}
