<?php

namespace App\Modules\Billing\Enums;

use App\Modules\Property\Enums\AllocationCategory;
use Filament\Support\Contracts\HasLabel;

/**
 * Invoice components (FR-BIL-02). Each maps to the category payments are
 * allocated by (PRD §8.5).
 */
enum InvoiceItemType: string implements HasLabel
{
    case Rent = 'rent';
    case Utility = 'utility';
    case Deposit = 'deposit';
    case Addon = 'addon';
    case Damage = 'damage';
    case Discount = 'discount';
    case Rounding = 'rounding';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rent => 'Sewa',
            self::Utility => 'Utilitas',
            self::Deposit => 'Deposit',
            self::Addon => 'Layanan tambahan',
            self::Damage => 'Ganti rugi kerusakan',
            self::Discount => 'Diskon',
            self::Rounding => 'Pembulatan',
            self::Other => 'Biaya lain',
        };
    }

    public function allocationCategory(): AllocationCategory
    {
        return match ($this) {
            self::Rent, self::Discount => AllocationCategory::Rent,
            self::Utility => AllocationCategory::Utility,
            self::Deposit => AllocationCategory::Deposit,
            self::Addon => AllocationCategory::Addon,
            self::Damage, self::Rounding, self::Other => AllocationCategory::Other,
        };
    }

    /**
     * Types staff can add by hand to an ad-hoc invoice.
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        $options = [];

        foreach ([self::Damage, self::Other, self::Addon, self::Utility, self::Rent, self::Discount] as $type) {
            $options[$type->value] = $type->getLabel();
        }

        return $options;
    }
}
