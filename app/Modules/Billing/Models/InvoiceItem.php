<?php

namespace App\Modules\Billing\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Billing\Database\Factories\InvoiceItemFactory;
use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Property\Enums\AllocationCategory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Money\RupiahCast;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of an invoice. Frozen once the invoice is issued.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property InvoiceItemType $type
 * @property AllocationCategory $allocation_category
 * @property string $description
 * @property string $quantity
 * @property int $unit_amount
 * @property int $amount
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $source_type
 * @property string|null $source_id
 * @property int $sort_order
 */
#[Fillable([
    'invoice_id', 'type', 'allocation_category', 'description', 'quantity', 'unit_amount', 'amount',
    'period_start', 'period_end', 'source_type', 'source_id', 'sort_order',
])]
#[UseFactory(InvoiceItemFactory::class)]
class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    protected static function booted(): void
    {
        $guard = function (self $item): void {
            $status = Invoice::query()->whereKey($item->invoice_id)->toBase()->value('status');

            if ($status !== null && $status !== Draft::$name) {
                throw new LogicException('Baris tagihan yang sudah terbit tidak dapat diubah.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => InvoiceItemType::class,
            'allocation_category' => AllocationCategory::class,
            'quantity' => 'decimal:3',
            'unit_amount' => RupiahCast::class,
            'amount' => RupiahCast::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'sort_order' => 'integer',
        ];
    }
}
