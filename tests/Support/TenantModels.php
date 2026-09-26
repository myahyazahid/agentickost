<?php

namespace Tests\Support;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds the models in app/Modules/{Name}/Models, so tenant isolation tests
 * cover each new model without being edited.
 */
final class TenantModels
{
    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        $models = [];

        foreach (glob(dirname(__DIR__, 2).'/app/Modules/*/Models/*.php') ?: [] as $file) {
            $class = 'App\\Modules\\'.basename(dirname($file, 2)).'\\Models\\'.basename($file, '.php');

            if (is_subclass_of($class, Model::class)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function scoped(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $model): bool => in_array(BelongsToTenant::class, class_uses_recursive($model), true),
        ));
    }
}
