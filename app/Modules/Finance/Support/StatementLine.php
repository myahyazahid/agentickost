<?php

namespace App\Modules\Finance\Support;

/**
 * One line of a financial statement: an account, or a group of cash
 * movements, with its amount in whole rupiah.
 */
final readonly class StatementLine
{
    public function __construct(
        public string $label,
        public int $amount,
        public ?string $code = null,
    ) {}
}
