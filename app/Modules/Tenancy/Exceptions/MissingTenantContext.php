<?php

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

final class MissingTenantContext extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Tidak ada konteks tenant. Query data tenant ditolak (NFR-ISO-01).');
    }
}
