<?php

declare(strict_types=1);
use VendorName\Skeleton\Skeleton;

it('loads the package class', function () {
    expect(class_exists(Skeleton::class))->toBeTrue();
});
