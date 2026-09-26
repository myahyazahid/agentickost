<?php

namespace App\Modules\Tenancy\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class TenantMismatch extends RuntimeException
{
    public static function forModel(Model $model, ?string $currentTenantId): self
    {
        return new self(sprintf(
            'Data %s milik tenant %s tidak boleh diubah dari konteks tenant %s.',
            $model::class,
            $model->getAttribute('tenant_id') ?? '(kosong)',
            $currentTenantId ?? '(kosong)',
        ));
    }

    public static function immutable(Model $model): self
    {
        return new self(sprintf('tenant_id pada %s tidak boleh diubah.', $model::class));
    }
}
