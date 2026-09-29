<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\States\FiscalPeriod\Closed;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\States\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Closes a month of the books (FR-ACC-08, PRD §8.11): from then on no
 * journal may be dated in it, so reports for the month stay as they were.
 * Only a month that has ended can be closed.
 */
final class CloseFiscalPeriod extends Action
{
    public function __construct(
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input  year, month
     */
    public function handle(array $input): FiscalPeriod
    {
        $this->authorize('close', FiscalPeriod::class);

        $data = $this->validate($input, [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $start = CarbonImmutable::create((int) $data['year'], (int) $data['month'], 1);
        $today = CarbonImmutable::now($this->tenants->tenant()->default_timezone)->toDateString();

        if ($start === null || $start->endOfMonth()->toDateString() >= $today) {
            throw ValidationException::withMessages(['month' => 'Bulan ini belum berakhir, jadi belum bisa ditutup.']);
        }

        return $this->transaction(function () use ($start): FiscalPeriod {
            $period = FiscalPeriod::for($start);
            $period = FiscalPeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if (! $period->isOpen()) {
                throw ValidationException::withMessages(['month' => "Periode {$period->label()} sudah ditutup."]);
            }

            $actor = $this->actors->current();
            $period->closed_by = $actor->type === ActorType::User ? $actor->id : $actor->user?->getAuthIdentifier();
            $period->closed_at = now();
            StateTransition::to($period->status, Closed::class, 'month');

            return $period;
        });
    }
}
