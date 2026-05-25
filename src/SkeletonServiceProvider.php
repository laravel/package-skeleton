<?php

declare(strict_types=1);

namespace VendorName\Skeleton;

use Illuminate\Support\ServiceProvider;
use VendorName\Skeleton\Console\Commands\SkeletonCommand;

class SkeletonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/skeleton.php', 'skeleton');

        $this->app->singleton(Skeleton::class);
    }

    public function boot(): void
    {
        $this->bootConfig();
        $this->bootRoutes();
        $this->bootViews();
        $this->bootTranslations();
        $this->bootMigrations();
        $this->bootAssets();
        $this->bootCommands();
    }

    private function bootConfig(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/skeleton.php' => config_path('skeleton.php'),
        ], ['skeleton', 'skeleton-config']);
    }

    private function bootRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/skeleton.php');
    }

    private function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'skeleton');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/skeleton'),
        ], ['skeleton', 'skeleton-views']);
    }

    private function bootTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'skeleton');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/skeleton'),
        ], ['skeleton', 'skeleton-lang']);
    }

    private function bootMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['skeleton', 'skeleton-migrations']);
    }

    private function bootAssets(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/skeleton'),
        ], ['skeleton', 'skeleton-assets']);
    }

    private function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SkeletonCommand::class,
        ]);
    }
}
