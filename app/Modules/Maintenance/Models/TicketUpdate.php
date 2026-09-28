<?php

namespace App\Modules\Maintenance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Maintenance\Database\Factories\TicketUpdateFactory;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Actors\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry of a ticket's history: a status change or a note.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $ticket_id
 * @property ActorType $actor_type
 * @property string|null $actor_id
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $note
 * @property Carbon $created_at
 */
#[Fillable(['ticket_id', 'actor_type', 'actor_id', 'from_status', 'to_status', 'note'])]
#[UseFactory(TicketUpdateFactory::class)]
class TicketUpdate extends Model
{
    /** @use HasFactory<TicketUpdateFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Riwayat tiket tidak dapat diubah.'));
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
        ];
    }
}
