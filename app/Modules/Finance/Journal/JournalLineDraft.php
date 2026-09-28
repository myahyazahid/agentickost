<?php

namespace App\Modules\Finance\Journal;

/**
 * One side of a journal before it is posted. A positive amount is a debit,
 * a negative amount a credit.
 */
final readonly class JournalLineDraft
{
    public function __construct(
        public string $accountId,
        public int $amount,
        public ?string $contractId = null,
        public ?string $propertyId = null,
        public ?string $memo = null,
    ) {}

    public static function debit(string $accountId, int $amount, ?string $contractId = null, ?string $propertyId = null): self
    {
        return new self($accountId, $amount, $contractId, $propertyId);
    }

    public static function credit(string $accountId, int $amount, ?string $contractId = null, ?string $propertyId = null): self
    {
        return new self($accountId, -$amount, $contractId, $propertyId);
    }

    public function negated(): self
    {
        return new self($this->accountId, -$this->amount, $this->contractId, $this->propertyId, $this->memo);
    }
}
