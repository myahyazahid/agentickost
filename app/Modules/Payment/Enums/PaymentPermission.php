<?php

namespace App\Modules\Payment\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum PaymentPermission: string implements DefinesPermissions
{
    case ViewPayments = 'payment.view';
    case RecordPayments = 'payment.record';
    case VerifyPayments = 'payment.verify';
    case ReversePayments = 'payment.reverse';
    case ApplyCredit = 'credit.apply';
    case HandOverCash = 'cash.handover';
    case ConfirmHandovers = 'cash.confirm';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewPayments => Role::staff(),
            self::RecordPayments, self::HandOverCash => [Role::Owner, Role::Manager, Role::Caretaker],
            self::VerifyPayments, self::ApplyCredit => [Role::Owner, Role::Manager],
            self::ReversePayments, self::ConfirmHandovers => [Role::Owner],
        };
    }
}
