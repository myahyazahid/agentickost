<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Database\Factories\UserFactory;
use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Auth\UsesAuthenticatorApp;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Traits\HasRoles;

/**
 * Owner or staff of one tenant. Residents are stored elsewhere.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasRoles, HasUlids, MustVerifyEmail, Notifiable, SoftDeletes, UsesAuthenticatorApp;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The staff member logged in on the `web` guard.
     *
     * @throws AuthenticationException
     */
    public static function current(): self
    {
        $user = Auth::guard('web')->user();

        return $user instanceof self ? $user : throw new AuthenticationException;
    }

    /**
     * Staff of a frozen tenant are locked out (FR-TNT-06).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'app'
            && $this->is_active
            && Tenant::query()->whereKey($this->tenant_id)->active()->exists();
    }

    /**
     * Sends the app panel's verification link, the same one Filament's
     * registration and resend buttons send, rather than Laravel's default
     * link to a route this app does not have.
     */
    public function sendEmailVerificationNotification(): void
    {
        $notification = app(VerifyEmail::class);
        $notification->url = Filament::getPanel('app')->getVerifyEmailUrl($this);

        $this->notify($notification);
    }

    /**
     * Owners and accountants see every property; other staff only the
     * properties assigned to them (FR-USR-02).
     */
    public function seesAllProperties(): bool
    {
        $roles = array_filter(Role::staff(), fn (Role $role): bool => $role->seesAllProperties());

        return $this->hasAnyRole(array_map(fn (Role $role): string => $role->value, $roles));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
