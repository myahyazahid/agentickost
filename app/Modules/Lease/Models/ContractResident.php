<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Lease\Database\Factories\ContractResidentFactory;
use App\Modules\Lease\Enums\ShareType;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A resident living under a contract (FR-KTR-02). The share is information
 * only: a contract gets one invoice (docs/adr/0008-keputusan-mvp.md).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property string $resident_id
 * @property bool $is_primary
 * @property ShareType|null $share_type
 * @property int|null $share_amount
 * @property Carbon $joined_on
 * @property Carbon|null $left_on
 */
#[UseFactory(ContractResidentFactory::class)]
class ContractResident extends Pivot
{
    /** @use HasFactory<ContractResidentFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public $incrementing = false;

    protected $table = 'contract_residents';

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'share_type' => ShareType::class,
            'share_amount' => RupiahCast::class,
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }
}
