<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Database\Factories\TenantFactory;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Events\TenantCreated;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A kost business subscribed to the platform. Platform table: not tenant-scoped.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_path
 * @property string|null $brand_color
 * @property string $default_timezone
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $frozen_at
 * @property string|null $frozen_reason
 * @property Carbon $created_at
 * @property array<string, mixed> $settings
 */
#[Fillable(['name', 'slug', 'logo_path', 'brand_color', 'default_timezone', 'trial_ends_at', 'settings'])]
#[UseFactory(TenantFactory::class)]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'default_timezone' => 'Asia/Jakarta',
        'settings' => '{}',
    ];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TenantCreated::class,
    ];

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    public function status(): TenantStatus
    {
        return match (true) {
            $this->isFrozen() => TenantStatus::Frozen,
            $this->trial_ends_at === null => TenantStatus::Active,
            $this->trial_ends_at->isFuture() => TenantStatus::Trial,
            default => TenantStatus::TrialEnded,
        };
    }

    /**
     * Days of trial left, counting today; null outside a running trial.
     */
    public function trialDaysLeft(): ?int
    {
        if ($this->status() !== TenantStatus::Trial || $this->trial_ends_at === null) {
            return null;
        }

        return (int) ceil(now()->diffInDays($this->trial_ends_at, absolute: true));
    }

    /**
     * @return HasMany<ImpersonationLog, $this>
     */
    public function impersonationLogs(): HasMany
    {
        return $this->hasMany(ImpersonationLog::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('frozen_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'frozen_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
