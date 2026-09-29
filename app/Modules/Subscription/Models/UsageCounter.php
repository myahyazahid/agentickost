<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Access\Concerns\Auditable;
use App\Modules\Subscription\Database\Factories\UsageCounterFactory;
use App\Modules\Subscription\Enums\UsageMetric;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How much of a monthly quota a tenant used (schema §13.4), for messages and
 * AI credits once those features ship.
 *
 * @property string $id
 * @property string $tenant_id
 * @property UsageMetric $metric
 * @property string $period
 * @property int $used
 */
#[Fillable(['metric', 'period', 'used'])]
#[UseFactory(UsageCounterFactory::class)]
class UsageCounter extends Model
{
    /** @use HasFactory<UsageCounterFactory> */
    use Auditable, BelongsToTenant, HasFactory, HasUlids;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'metric' => UsageMetric::class,
            'used' => 'integer',
        ];
    }
}
