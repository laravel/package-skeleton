<?php

declare(strict_types=1);

namespace VendorName\Skeleton;

use Closure;
use Illuminate\Support\ServiceProvider;
/* @chisel-commands */
use VendorName\Skeleton\Console\Commands\SkeletonCommand;

/* @end-chisel-commands */

class SkeletonServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /* @chisel-config */
        $this->mergeConfigFrom(__DIR__.'/../config/skeleton.php', 'skeleton');
        /* @end-chisel-config */

        $this->app->singleton(Skeleton::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* @chisel-config */
        $this->whenInConsole(fn () => $this->publishes([
            __DIR__.'/../config/skeleton.php' => config_path('skeleton.php'),
        ], ['skeleton', 'skeleton-config']));
        /* @end-chisel-config */

        /* @chisel-routes */
        $this->loadRoutesFrom(__DIR__.'/../routes/skeleton.php');
        /* @end-chisel-routes */

        /* @chisel-views */
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'skeleton');

        $this->whenInConsole(fn () => $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/skeleton'),
        ], ['skeleton', 'skeleton-views']));
        /* @end-chisel-views */

        /* @chisel-translations */
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'skeleton');

        $this->whenInConsole(fn () => $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/skeleton'),
        ], ['skeleton', 'skeleton-lang']));
        /* @end-chisel-translations */

        /* @chisel-migrations */
        $this->whenInConsole(fn () => $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['skeleton', 'skeleton-migrations']));
        /* @end-chisel-migrations */

        /* @chisel-assets */
        $this->whenInConsole(fn () => $this->publishes([
            __DIR__.'/../public' => public_path('vendor/skeleton'),
        ], ['skeleton', 'skeleton-assets']));
        /* @end-chisel-assets */

        /* @chisel-commands */
        $this->whenInConsole(fn () => $this->commands([
            SkeletonCommand::class,
        ]));
        /* @end-chisel-commands */
    }

    private function whenInConsole(Closure $callback): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $callback();
    }
}
