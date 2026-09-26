<?php

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider for a module in app/Modules/{Name}.
 *
 * Loads, when present: Database/Migrations, Resources/views (as the
 * "{name}::" view namespace, e.g. "lease::pdf.contract"), and routes.php
 * (inside the "web" middleware group). Subclasses that override boot() must
 * call parent::boot().
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $migrations = $this->modulePath('Database/Migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $views = $this->modulePath('Resources/views');

        if (is_dir($views)) {
            $this->loadViewsFrom($views, Str::lower(basename($this->modulePath())));
        }

        $routes = $this->modulePath('routes.php');

        if (is_file($routes) && ! $this->app->routesAreCached()) {
            Route::middleware('web')->group($routes);
        }
    }

    protected function modulePath(string $path = ''): string
    {
        $directory = dirname((string) (new ReflectionClass($this))->getFileName());

        return $path === '' ? $directory : $directory.DIRECTORY_SEPARATOR.$path;
    }
}
