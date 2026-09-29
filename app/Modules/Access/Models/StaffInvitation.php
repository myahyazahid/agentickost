<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Database\Factories\StaffInvitationFactory;
use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An emailed invitation to join the tenant as staff (FR-USR-03). Only a
 * hash of the link's token is stored. The staff member picks a password
 * when accepting; the account is created then.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property list<string> $property_ids
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $cancelled_at
 * @property string|null $user_id
 * @property string|null $invited_by
 * @property Carbon $created_at
 */
#[Fillable(['name', 'email', 'role', 'property_ids', 'token_hash', 'expires_at', 'invited_by'])]
#[Hidden(['token_hash'])]
#[UseFactory(StaffInvitationFactory::class)]
class StaffInvitation extends Model
{
    /** @use HasFactory<StaffInvitationFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    public const VALID_DAYS = 7;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The invitation behind a link. The token is the only key a new staff
     * member has, before any tenant is known, so this lookup crosses
     * tenants; the token is a random secret.
     */
    public static function findByToken(string $token): ?self
    {
        return self::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('token_hash', self::hashToken($token))
            ->first();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->cancelled_at === null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->cancelled_at === null && ! $this->expires_at->isFuture();
    }

    /**
     * Not yet accepted or cancelled, including expired ones that can be
     * sent again.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'property_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
