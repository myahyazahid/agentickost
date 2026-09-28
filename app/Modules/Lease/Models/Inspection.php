<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Lease\Database\Factories\InspectionFactory;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Property\Models\Room;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The room's condition at check-in or check-out, item by item, with photos
 * (FR-SIK-01, FR-SIK-04).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $contract_id
 * @property string $room_id
 * @property InspectionType $type
 * @property Carbon $inspected_on
 * @property string $inspector_id
 * @property Carbon|null $resident_acknowledged_at
 * @property string|null $notes
 */
#[Fillable(['contract_id', 'room_id', 'type', 'inspected_on', 'inspector_id', 'resident_acknowledged_at', 'notes'])]
#[UseFactory(InspectionFactory::class)]
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use Auditable, BelongsToTenant, HasAttachments, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * @return HasMany<InspectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('sort_order');
    }

    public function chargedAmount(): int
    {
        return (int) $this->items()->sum('charge_amount');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => InspectionType::class,
            'inspected_on' => 'date',
            'resident_acknowledged_at' => 'datetime',
        ];
    }
}
