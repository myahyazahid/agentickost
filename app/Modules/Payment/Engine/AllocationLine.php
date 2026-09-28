<?php

namespace App\Modules\Payment\Engine;

use App\Modules\Property\Enums\AllocationCategory;

final readonly class AllocationLine
{
    public function __construct(
        public string $invoiceId,
        public AllocationCategory $category,
        public int $amount,
    ) {}
}
