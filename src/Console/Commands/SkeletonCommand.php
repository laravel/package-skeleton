<?php

declare(strict_types=1);

namespace VendorName\Skeleton\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('skeleton:placeholder')]
#[Description('Placeholder Artisan command shipped by the package skeleton.')]
class SkeletonCommand extends Command
{
    public function handle(): int
    {
        $this->line('Skeleton placeholder command executed.');

        return self::SUCCESS;
    }
}
