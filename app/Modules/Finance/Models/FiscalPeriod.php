<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\FiscalPeriodFactory;
use App\Modules\Finance\States\FiscalPeriod\FiscalPeriodState;
use App\Modules\Finance\States\FiscalPeriod\Open;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\States\EnforcesStateTransitions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * A calendar month of the tenant's books (schema §10.2).
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $year
 * @property int $month
 * @property FiscalPeriodState $status
 * @property string|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $reopened_by
 * @property Carbon|null $reopened_at
 * @property string|null $reopen_reason
 */
#[Fillable(['year', 'month'])]
#[UseFactory(FiscalPeriodFactory::class)]
class FiscalPeriod extends Model
{
    /** @use HasFactory<FiscalPeriodFactory> */
    use Auditable, BelongsToTenant, EnforcesStateTransitions, HasFactory, HasStates, HasUlids;

    /**
     * The period a business date falls in, opened on first use.
     */
    public static function for(CarbonInterface $date): self
    {
        return self::query()->firstOrCreate(['year' => $date->year, 'month' => $date->month]);
    }

    public function isOpen(): bool
    {
        return $this->status->equals(Open::class);
    }

    public function label(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'status' => FiscalPeriodState::class,
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }
}
