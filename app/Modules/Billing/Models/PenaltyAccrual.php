<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\PenaltyAccrualFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A late penalty charged on an invoice for one day (PRD §8.4). Kept apart
 * from the invoice lines so the issued invoice itself never changes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property Carbon $accrued_on
 * @property int $amount
 * @property array<string, mixed> $rule_snapshot
 * @property Carbon|null $waived_at
 * @property string|null $waived_by
 * @property string|null $waive_reason
 */
#[Fillable(['invoice_id', 'accrued_on', 'amount', 'rule_snapshot'])]
#[UseFactory(PenaltyAccrualFactory::class)]
class PenaltyAccrual extends Model
{
    /** @use HasFactory<PenaltyAccrualFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isWaived(): bool
    {
        return $this->waived_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'accrued_on' => 'date',
            'amount' => RupiahCast::class,
            'rule_snapshot' => 'array',
            'waived_at' => 'datetime',
        ];
    }
}
