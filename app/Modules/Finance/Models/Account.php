<?php

namespace App\Modules\Finance\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Finance\Database\Factories\AccountFactory;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Chart of accounts entry (FR-ACC-01).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property AccountSubtype|null $subtype
 * @property string|null $parent_id
 * @property string|null $property_id
 * @property string|null $user_id
 * @property bool $is_system
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'type', 'subtype', 'parent_id', 'property_id', 'user_id', 'is_system', 'is_active'])]
#[UseFactory(AccountFactory::class)]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    /**
     * The tenant's top-level system account for a subtype, such as the
     * receivable account.
     */
    public static function system(AccountSubtype $subtype): self
    {
        return self::query()
            ->where('subtype', $subtype->value)
            ->where('is_system', true)
            ->whereNull('parent_id')
            ->firstOrFail();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
