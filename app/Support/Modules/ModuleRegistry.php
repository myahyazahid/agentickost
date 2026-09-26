<?php

namespace App\Support\Modules;

/**
 * Discovers modules by convention: app/Modules/{Name}/{Name}ServiceProvider.php.
 */
final class ModuleRegistry
{
    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::discover());
    }

    /**
     * @return list<class-string<ModuleServiceProvider>>
     */
    public static function providers(): array
    {
        return array_values(self::discover());
    }

    /**
     * @return array<string, class-string<ModuleServiceProvider>>
     */
    private static function discover(): array
    {
        $modules = [];

        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            $provider = "App\\Modules\\{$name}\\{$name}ServiceProvider";

            if (is_subclass_of($provider, ModuleServiceProvider::class)) {
                $modules[$name] = $provider;
            }
        }

        return $modules;
    }
}
