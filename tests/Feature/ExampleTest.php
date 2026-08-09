<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use VendorName\Skeleton\Skeleton;

it('registers the singleton', function () {
    expect(app()->bound(Skeleton::class))->toBeTrue();
});

it('returns the same instance from the container', function () {
    expect(app(Skeleton::class))->toBe(app(Skeleton::class));
});

/* @chisel-config */
it('merges the package config', function () {
    expect(config('skeleton.placeholder'))->toBe('default');
});
/* @end-chisel-config */

/* @chisel-translations */
it('loads the package translations', function () {
    expect(trans('skeleton::messages.placeholder'))->toBe('Skeleton placeholder translation.');
});
/* @end-chisel-translations */

/* @chisel-views */
it('loads the package views', function () {
    $this->view('skeleton::placeholder')
        ->assertSee('Skeleton placeholder view.');
});
/* @end-chisel-views */

/* @chisel-commands */
it('registers the artisan command', function () {
    Artisan::call('skeleton:placeholder');

    expect(Artisan::output())->toContain('Skeleton placeholder command executed.');
});
/* @end-chisel-commands */
