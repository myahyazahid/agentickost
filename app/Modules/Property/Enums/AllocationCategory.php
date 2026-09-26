<?php

namespace App\Modules\Property\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Components a payment is allocated to, in the order set per property
 * (PRD §8.5, docs/adr/0008-keputusan-mvp.md).
 */
enum AllocationCategory: string implements HasLabel
{
    case Deposit = 'deposit';
    case Rent = 'rent';
    case Utility = 'utility';
    case Addon = 'addon';
    case Other = 'other';
    case Penalty = 'penalty';

    public function getLabel(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Rent => 'Sewa',
            self::Utility => 'Utilitas',
            self::Addon => 'Layanan tambahan',
            self::Other => 'Biaya lain',
            self::Penalty => 'Denda',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Deposit => 'Uang jaminan yang ditagih di tagihan pertama.',
            self::Rent => 'Sewa kamar per periode.',
            self::Utility => 'Listrik, air, atau internet dari meteran dan tarif.',
            self::Addon => 'Layanan di luar sewa, misal laundry.',
            self::Other => 'Biaya lain, misal ganti rugi kerusakan.',
            self::Penalty => 'Denda keterlambatan.',
        };
    }

    /**
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return array_map(fn (self $category): string => $category->value, self::cases());
    }
}
