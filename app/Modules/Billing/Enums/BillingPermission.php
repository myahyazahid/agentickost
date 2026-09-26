<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Permissions\DefinesPermissions;

enum BillingPermission: string implements DefinesPermissions
{
    case ViewInvoices = 'invoice.view';
    case ManageInvoices = 'invoice.manage';
    case VoidInvoices = 'invoice.void';
    case IssueCreditNotes = 'invoice.credit';
    case WaivePenalties = 'penalty.waive';
    case ManageUtilityRates = 'utility.manage-rates';
    case RecordMeterReadings = 'meter.record';

    public function defaultRoles(): array
    {
        return match ($this) {
            self::ViewInvoices => Role::staff(),
            self::ManageInvoices => [Role::Owner, Role::Manager],
            self::RecordMeterReadings => [Role::Owner, Role::Manager, Role::Caretaker],
            self::VoidInvoices, self::IssueCreditNotes, self::WaivePenalties, self::ManageUtilityRates => [Role::Owner],
        };
    }
}
