<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Lease\Database\Factories\ContractHoldFactory;
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
 * A special rate while the resident is away, such as a semester break
 * (FR-KTR-05). Billing uses it for periods starting inside the hold.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property int $rent_amount
 * @property string|null $reason
 * @property string|null $created_by
 */
#[Fillable(['contract_id', 'start_date', 'end_date', 'rent_amount', 'reason', 'created_by'])]
#[UseFactory(ContractHoldFactory::class)]
class ContractHold extends Model
{
    /** @use HasFactory<ContractHoldFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => RupiahCast::class,
        ];
    }
}
