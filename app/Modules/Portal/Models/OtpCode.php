<?php

namespace App\Modules\Portal\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Portal\Database\Factories\OtpCodeFactory;
use App\Modules\Portal\Enums\OtpPurpose;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time login code sent to a phone (FR-PRT-01). Only its hash is
 * stored; wrong guesses are counted (NFR-SEC-03).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $phone
 * @property OtpPurpose $purpose
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon $created_at
 */
#[Fillable(['phone', 'purpose', 'code_hash', 'expires_at'])]
#[Hidden(['code_hash'])]
#[UseFactory(OtpCodeFactory::class)]
class OtpCode extends Model
{
    /** @use HasFactory<OtpCodeFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
