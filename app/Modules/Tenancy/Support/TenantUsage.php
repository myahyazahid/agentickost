<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * How much each tenant uses the platform, for the super admin (FR-TNT-04).
 * Counts come straight from the tables: the admin works outside any tenant,
 * where tenant models refuse to query.
 */
final class TenantUsage
{
    /**
     * Tenants with owner_email, properties_count, rooms_count,
     * running_contracts_count, and staff_count selected.
     *
     * @return Builder<Tenant>
     */
    public static function query(): Builder
    {
        return Tenant::query()
            ->select('tenants.*')
            ->selectSub(self::ownerEmail(), 'owner_email')
            ->selectSub(self::count('properties'), 'properties_count')
            ->selectSub(self::count('rooms'), 'rooms_count')
            // Running contracts: the Lease module's ContractState::runningValues().
            ->selectSub(self::count('contracts', fn (QueryBuilder $query) => $query->whereIn('status', ['active', 'notice']), softDeletes: false), 'running_contracts_count')
            ->selectSub(self::count('users', fn (QueryBuilder $query) => $query->where('is_active', true)), 'staff_count');
    }

    private static function ownerEmail(): QueryBuilder
    {
        return DB::table('users')
            ->join('model_has_roles', function ($join): void {
                $join->on('model_has_roles.model_id', '=', 'users.id')->where('model_has_roles.model_type', 'user');
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'owner')
            ->whereColumn('users.tenant_id', 'tenants.id')
            ->whereNull('users.deleted_at')
            ->orderBy('users.created_at')
            ->limit(1)
            ->select('users.email');
    }

    /**
     * @param  (callable(QueryBuilder): mixed)|null  $constraint
     */
    private static function count(string $table, ?callable $constraint = null, bool $softDeletes = true): QueryBuilder
    {
        $query = DB::table($table)->selectRaw('COUNT(*)')->whereColumn("{$table}.tenant_id", 'tenants.id');

        if ($softDeletes) {
            $query->whereNull("{$table}.deleted_at");
        }

        if ($constraint !== null) {
            $constraint($query);
        }

        return $query;
    }
}
