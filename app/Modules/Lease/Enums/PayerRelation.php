<?php

namespace App\Modules\Lease\Enums;

use Filament\Support\Contracts\HasLabel;

enum PayerRelation: string implements HasLabel
{
    case Self = 'self';
    case Parent = 'parent';
    case Guardian = 'guardian';
    case Company = 'company';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Self => 'Penghuni sendiri',
            self::Parent => 'Orang tua',
            self::Guardian => 'Wali',
            self::Company => 'Perusahaan',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Options for a payer who is not the resident.
     *
     * @return array<string, string>
     */
    public static function othersOptions(): array
    {
        $options = [];

        foreach (self::cases() as $relation) {
            if ($relation !== self::Self) {
                $options[$relation->value] = $relation->getLabel();
            }
        }

        return $options;
    }
}
