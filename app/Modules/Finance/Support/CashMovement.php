<?php

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\CashActivity;
use App\Modules\Finance\Enums\JournalEvent;

/**
 * Money that moved in (positive) or out (negative) of the cash accounts
 * for one purpose, named the way an owner would say it.
 */
final readonly class CashMovement
{
    public function __construct(
        public CashActivity $activity,
        public string $label,
        public int $amount,
    ) {}

    /**
     * Names the movement from the business event and the account on the
     * other side of the cash lines.
     */
    public static function classify(JournalEvent $event, AccountType $type, ?AccountSubtype $subtype, string $accountName, int $amount): self
    {
        $label = match (true) {
            in_array($event, [JournalEvent::PaymentVerified, JournalEvent::PaymentReversed], true) => 'Pembayaran dari penghuni',
            $event === JournalEvent::DepositRefunded => 'Deposit dikembalikan ke penghuni',
            $event === JournalEvent::CreditRefunded => 'Saldo kredit dikembalikan ke penghuni',
            default => $accountName,
        };

        $activity = match (true) {
            in_array($type, [AccountType::Revenue, AccountType::Expense], true) => CashActivity::Operating,
            in_array($subtype, [AccountSubtype::Receivable, AccountSubtype::DepositLiability, AccountSubtype::CreditLiability, AccountSubtype::AdvanceLiability], true) => CashActivity::Operating,
            $type === AccountType::Asset => CashActivity::Investing,
            default => CashActivity::Financing,
        };

        return new self($activity, $label, $amount);
    }

    public function plus(int $amount): self
    {
        return new self($this->activity, $this->label, $this->amount + $amount);
    }
}
