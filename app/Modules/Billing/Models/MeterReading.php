<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\MeterReadingFactory;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Property\Models\Room;
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
 * A meter reading of one room (FR-UTL-02). Usage is a generated column:
 * current minus previous value. The rate is a snapshot taken on the reading
 * date, so later rate changes do not alter it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $room_id
 * @property UtilityKind $utility
 * @property Carbon $reading_date
 * @property string $previous_value
 * @property string $current_value
 * @property string $usage
 * @property bool $is_meter_replaced
 * @property int $rate_amount
 * @property int $amount
 * @property string|null $invoice_item_id
 * @property string|null $recorded_by
 * @property string|null $client_uuid
 */
#[Fillable([
    'room_id', 'utility', 'reading_date', 'previous_value', 'current_value', 'is_meter_replaced',
    'rate_amount', 'amount', 'invoice_item_id', 'recorded_by', 'client_uuid',
])]
#[UseFactory(MeterReadingFactory::class)]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use Auditable, BelongsToTenant, HasAttachments, HasFactory, HasUlids;

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function isBilled(): bool
    {
        return $this->invoice_item_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'utility' => UtilityKind::class,
            'reading_date' => 'date',
            'previous_value' => 'decimal:2',
            'current_value' => 'decimal:2',
            'usage' => 'decimal:2',
            'is_meter_replaced' => 'boolean',
            'rate_amount' => RupiahCast::class,
            'amount' => RupiahCast::class,
        ];
    }
}
