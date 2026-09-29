<?php

namespace App\Modules\Reports\Support;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\TicketState;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Maintenance;
use App\Modules\Property\States\Room\Occupied;
use App\Modules\Property\States\Room\Vacating;
use Carbon\CarbonImmutable;

/**
 * The numbers on the dashboard (FR-RPT-01), limited to the properties the
 * user can see. "This month" is the calendar month in the tenant's time
 * zone.
 */
final class DashboardFigures
{
    public function __construct(
        private readonly User $user,
        private readonly CarbonImmutable $today,
    ) {}

    /**
     * @return array{rooms: int, occupied: int, maintenance: int, percent: int|null}
     */
    public function occupancy(): array
    {
        $rooms = Room::query()->accessibleBy($this->user);
        $total = (clone $rooms)->count();
        $occupied = (clone $rooms)->whereIn('status', [Occupied::$name, Vacating::$name])->count();

        return [
            'rooms' => $total,
            'occupied' => $occupied,
            'maintenance' => (clone $rooms)->where('status', Maintenance::$name)->count(),
            'percent' => $total > 0 ? (int) round($occupied / $total * 100) : null,
        ];
    }

    /**
     * Income booked this month, from the ledger: rent, utilities,
     * penalties, and other income on invoices issued this month, less
     * credit notes and voids.
     */
    public function revenueThisMonth(): int
    {
        $lines = JournalLine::query()
            ->whereHas('account', fn ($query) => $query->where('type', AccountType::Revenue->value))
            ->whereHas('entry', fn ($query) => $query->whereBetween('entry_date', [
                $this->today->startOfMonth()->toDateString(),
                $this->today->endOfMonth()->toDateString(),
            ]));

        return (int) (clone $lines)->sum('credit_amount') - (int) (clone $lines)->sum('debit_amount');
    }

    /**
     * Money verified as received this month.
     */
    public function receivedThisMonth(): int
    {
        return (int) Payment::query()
            ->accessibleBy($this->user)
            ->where('status', Verified::$name)
            ->whereBetween('paid_at', [
                $this->today->startOfMonth()->utc(),
                $this->today->endOfMonth()->utc(),
            ])
            ->sum('amount');
    }

    /**
     * @return array{amount: int, contracts: int}
     */
    public function arrears(): array
    {
        $overdue = Overdue::invoices($this->user);

        return [
            'amount' => (int) (clone $overdue)->sum('balance_amount'),
            'contracts' => (clone $overdue)->distinct()->count('contract_id'),
        ];
    }

    /**
     * @return array{open: int, pressing: int}
     */
    public function openTickets(): array
    {
        $open = Ticket::query()->accessibleBy($this->user)->whereIn('status', TicketState::openValues());

        return [
            'open' => (clone $open)->count(),
            'pressing' => (clone $open)->whereIn('priority', [TicketPriority::High->value, TicketPriority::Urgent->value])->count(),
        ];
    }

    /**
     * @return array{count: int, amount: int}
     */
    public function pendingPayments(): array
    {
        $pending = Payment::query()->accessibleBy($this->user)->where('status', Pending::$name);

        return [
            'count' => (clone $pending)->count(),
            'amount' => (int) (clone $pending)->sum('amount'),
        ];
    }
}
