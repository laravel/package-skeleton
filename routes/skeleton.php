<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('skeleton', fn () => 'Skeleton placeholder route.')
    ->name('skeleton.placeholder');
