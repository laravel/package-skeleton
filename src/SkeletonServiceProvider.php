<?php

declare(strict_types=1);

namespace VendorName\Skeleton;

use Illuminate\Support\ServiceProvider;
/* @chisel-commands */
use VendorName\Skeleton\Console\Commands\SkeletonCommand;

/* @end-chisel-commands */

class SkeletonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /* @chisel-config */
        $this->mergeConfigFrom(__DIR__.'/../config/skeleton.php', 'skeleton');
        /* @end-chisel-config */

        $this->app->singleton(Skeleton::class);
    }

    public function boot(): void
    {
        /* @chisel-config */
        $this->bootConfig();
        /* @end-chisel-config */
        /* @chisel-routes */
        $this->bootRoutes();
        /* @end-chisel-routes */
        /* @chisel-views */
        $this->bootViews();
        /* @end-chisel-views */
        /* @chisel-translations */
        $this->bootTranslations();
        /* @end-chisel-translations */
        /* @chisel-migrations */
        $this->bootMigrations();
        /* @end-chisel-migrations */
        /* @chisel-assets */
        $this->bootAssets();
        /* @end-chisel-assets */
        /* @chisel-commands */
        $this->bootCommands();
        /* @end-chisel-commands */
    }

    /* @chisel-config */
    private function bootConfig(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/skeleton.php' => config_path('skeleton.php'),
        ], ['skeleton', 'skeleton-config']);
    }
    /* @end-chisel-config */

    /* @chisel-routes */
    private function bootRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/skeleton.php');
    }
    /* @end-chisel-routes */

    /* @chisel-views */
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
    /* @end-chisel-views */

    /* @chisel-translations */
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
    /* @end-chisel-translations */

    /* @chisel-migrations */
    private function bootMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['skeleton', 'skeleton-migrations']);
    }
    /* @end-chisel-migrations */

    /* @chisel-assets */
    private function bootAssets(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/skeleton'),
        ], ['skeleton', 'skeleton-assets']);
    }
    /* @end-chisel-assets */

    /* @chisel-commands */
    private function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SkeletonCommand::class,
        ]);
    }
    /* @end-chisel-commands */
}
