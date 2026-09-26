<?php

namespace App\Modules\Billing\Policies;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Property\Models\Property;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(BillingPermission::ViewInvoices->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(BillingPermission::ViewInvoices->value) && $invoice->isAccessibleBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can(BillingPermission::ManageInvoices->value);
    }

    public function createIn(User $user, Property $property): bool
    {
        return $user->can(BillingPermission::ManageInvoices->value) && $property->isAccessibleBy($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can(BillingPermission::ManageInvoices->value) && $invoice->isAccessibleBy($user);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can(BillingPermission::VoidInvoices->value) && $invoice->isAccessibleBy($user);
    }

    public function credit(User $user, Invoice $invoice): bool
    {
        return $user->can(BillingPermission::IssueCreditNotes->value) && $invoice->isAccessibleBy($user);
    }

    public function waivePenalty(User $user, Invoice $invoice): bool
    {
        return $user->can(BillingPermission::WaivePenalties->value) && $invoice->isAccessibleBy($user);
    }
}
