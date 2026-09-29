<?php

namespace App\Support\Modules;

use Filament\Panel;

/**
 * Lets a Filament panel pick up resources, pages, and widgets from
 * app/Modules/{Name}/Filament/{PanelDirectory}, and public panel routes
 * (such as an invitation link) from routes.php in that directory.
 */
trait DiscoversModuleComponents
{
    protected function discoverModuleComponents(Panel $panel, string $panelDirectory): Panel
    {
        foreach (ModuleRegistry::names() as $module) {
            $directory = app_path("Modules/{$module}/Filament/{$panelDirectory}");
            $namespace = "App\\Modules\\{$module}\\Filament\\{$panelDirectory}";

            $panel
                ->discoverResources(in: "{$directory}/Resources", for: "{$namespace}\\Resources")
                ->discoverPages(in: "{$directory}/Pages", for: "{$namespace}\\Pages")
                ->discoverWidgets(in: "{$directory}/Widgets", for: "{$namespace}\\Widgets");

            if (is_file("{$directory}/routes.php")) {
                $panel->routes(fn () => require "{$directory}/routes.php");
            }
        }

        return $panel;
    }
}
