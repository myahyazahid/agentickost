<?php

namespace App\Modules\Lease\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Lease\Database\Factories\ResidentFactory;
use App\Modules\Lease\Enums\Gender;
use App\Modules\Lease\Enums\IdentityType;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Lease\Support\IdentityHasher;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Carbon;

/**
 * A person renting a room (FR-PNH-01). Identity numbers are encrypted
 * (NFR-SEC-01) and hidden from arrays, so they never reach the audit log.
 * Logs in to the resident portal on the `resident` guard with a code sent
 * to their phone (FR-PRT-01); there is no password.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $full_name
 * @property string $phone
 * @property string|null $email
 * @property Gender|null $gender
 * @property Carbon|null $birth_date
 * @property IdentityType|null $identity_type
 * @property string|null $identity_number
 * @property string|null $identity_number_hash
 * @property string|null $institution
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relation
 * @property string|null $vehicle_plate
 * @property string|null $internal_notes
 * @property bool $is_flagged
 * @property Carbon|null $anonymized_at
 */
#[Fillable([
    'full_name', 'phone', 'email', 'gender', 'birth_date', 'identity_type', 'identity_number',
    'institution', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
    'vehicle_plate', 'internal_notes', 'is_flagged',
])]
#[Hidden(['identity_number', 'identity_number_hash', 'remember_token'])]
#[UseFactory(ResidentFactory::class)]
class Resident extends Model implements AuthenticatableContract, AuthorizableContract
{
    /** @use HasFactory<ResidentFactory> */
    use Auditable, Authenticatable, Authorizable, BelongsToTenant, HasAttachments, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_flagged' => false,
    ];

    /**
     * Portal logins use a one-time code, never a password.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    protected static function booted(): void
    {
        static::saving(function (self $resident): void {
            if ($resident->isDirty('identity_number')) {
                $resident->identity_number_hash = IdentityHasher::hash($resident->identity_number);
            }
        });
    }

    /**
     * @return BelongsToMany<Contract, $this, ContractResident>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_residents')
            ->using(ContractResident::class)
            ->withPivot(['is_primary', 'joined_on', 'left_on']);
    }

    /**
     * The contract the resident currently lives under, if any.
     */
    public function runningContract(): ?Contract
    {
        return $this->contracts()
            ->whereIn('contracts.status', ContractState::runningValues())
            ->wherePivotNull('left_on')
            ->first();
    }

    /**
     * Staff limited to some properties see residents of those properties,
     * plus residents who have no contract yet (FR-USR-02).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        if ($user->seesAllProperties()) {
            return;
        }

        $query->where(fn (Builder $visible) => $visible
            ->whereDoesntHave('contracts')
            ->orWhereHas('contracts', fn (Builder $contracts) => $contracts->whereIn(
                'contracts.property_id',
                Property::query()->accessibleBy($user)->select('properties.id'),
            )));
    }

    public function isAccessibleBy(User $user): bool
    {
        return self::query()->accessibleBy($user)->whereKey($this->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'identity_type' => IdentityType::class,
            'identity_number' => 'encrypted',
            'is_flagged' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }
}
