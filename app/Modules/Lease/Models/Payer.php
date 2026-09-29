<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Lease\Database\Factories\PayerFactory;
use App\Modules\Lease\Enums\PayerRelation;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Carbon;

/**
 * Who is billed for a contract (FR-PNH-03). When residents pay for
 * themselves, resident_id is set and relation is "self". A payer who does
 * not live in the room logs in to the portal on the `payer` guard and sees
 * only the bills they pay (FR-PRT-07).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $resident_id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property PayerRelation $relation
 * @property Carbon|null $anonymized_at
 */
#[Fillable(['resident_id', 'name', 'phone', 'email', 'relation'])]
#[Hidden(['remember_token'])]
#[UseFactory(PayerFactory::class)]
class Payer extends Model implements AuthenticatableContract, AuthorizableContract
{
    /** @use HasFactory<PayerFactory> */
    use Auditable, Authenticatable, Authorizable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * Portal logins use a one-time code, never a password.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'relation' => PayerRelation::class,
            'anonymized_at' => 'datetime',
        ];
    }
}
