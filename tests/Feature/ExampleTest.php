<?php

declare(strict_types=1);

use VendorName\Skeleton\Skeleton;

it('resolves the singleton', function () {
    expect(app(Skeleton::class))->toBeInstanceOf(Skeleton::class);
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
    expect(view()->exists('skeleton::placeholder'))->toBeTrue();
});
/* @end-chisel-views */

/* @chisel-commands */
it('registers the artisan command', function () {
    $this->artisan('skeleton:placeholder')
        ->expectsOutputToContain('Skeleton placeholder command executed.')
        ->assertSuccessful();
});
/* @end-chisel-commands */
