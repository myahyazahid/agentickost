<?php

namespace App\Support\Modules;

use Filament\Panel;

/**
 * Lets a Filament panel pick up resources, pages, and widgets from
 * app/Modules/{Name}/Filament/{PanelDirectory}.
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
        }

        return $panel;
    }
}
