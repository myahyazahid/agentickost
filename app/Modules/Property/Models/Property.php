<?php

namespace App\Modules\Property\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Access\Models\User;
use App\Modules\Documents\Concerns\HasAttachments;
use App\Modules\Property\Database\Factories\PropertyFactory;
use App\Modules\Property\Enums\GenderPolicy;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Support\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property string $address
 * @property string $city
 * @property string $province
 * @property string|null $postal_code
 * @property Timezone $timezone
 * @property GenderPolicy $gender_policy
 * @property string|null $rules
 * @property list<string>|null $facilities
 */
#[Fillable([
    'name', 'code', 'address', 'city', 'province', 'postal_code',
    'timezone', 'gender_policy', 'rules', 'facilities',
])]
#[UseFactory(PropertyFactory::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use Auditable, BelongsToTenant, HasAttachments, HasFactory, HasUlids, SoftDeletes;

    /**
     * @return HasOne<PropertySetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(PropertySetting::class);
    }

    /**
     * @return HasMany<RoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * The property's settings. CreateProperty writes them; properties made any
     * other way (imports, factories) get the defaults on first use.
     */
    public function resolvedSettings(): PropertySetting
    {
        $settings = $this->settings()->first();

        if ($settings !== null) {
            return $settings;
        }

        $settings = new PropertySetting(PropertySetting::defaults());
        $settings->tenant_id = $this->tenant_id;
        $settings->property_id = $this->id;
        $settings->save();

        return $settings;
    }

    /**
     * Staff assigned to the property.
     *
     * @return BelongsToMany<User, $this, PropertyUser>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->using(PropertyUser::class);
    }

    /**
     * Today's calendar date in the property's time zone (NFR-LOC-02).
     *
     * Business dates (due dates, contract dates, penalty days) are calendar
     * dates. They are returned the way DATE columns are read, at midnight in
     * the app time zone, so comparing them with date attributes compares
     * days rather than instants.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->timezone->value)->toDateString());
    }

    public function isAccessibleBy(User $user): bool
    {
        return $user->seesAllProperties()
            || PropertyUser::query()->where('property_id', $this->id)->where('user_id', $user->id)->exists();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        if ($user->seesAllProperties()) {
            return;
        }

        $query->whereIn('id', PropertyUser::query()->select('property_id')->where('user_id', $user->id));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'timezone' => Timezone::class,
            'gender_policy' => GenderPolicy::class,
            'facilities' => 'array',
        ];
    }
}
