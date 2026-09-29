<?php

namespace App\Modules\Portal\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Portal\Database\Factories\AnnouncementFactory;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * News for the residents of one property, such as water maintenance or a
 * rule change, shown in the portal once published (FR-PRT-05).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $property_id
 * @property string $title
 * @property string $body
 * @property Carbon|null $published_at
 * @property string|null $created_by
 * @property Carbon $created_at
 */
#[Fillable(['property_id', 'title', 'body', 'published_at', 'created_by'])]
#[UseFactory(AnnouncementFactory::class)]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public function isPublished(): bool
    {
        return $this->published_at !== null && ! $this->published_at->isFuture();
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
