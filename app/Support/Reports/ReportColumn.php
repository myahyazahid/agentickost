<?php

namespace App\Support\Reports;

final readonly class ReportColumn
{
    public function __construct(
        public string $label,
        public bool $isMoney = false,
    ) {}

    public static function text(string $label): self
    {
        return new self($label);
    }

    public static function money(string $label): self
    {
        return new self($label, isMoney: true);
    }
}
