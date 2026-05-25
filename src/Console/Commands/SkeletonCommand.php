<?php

declare(strict_types=1);

namespace VendorName\Skeleton\Console\Commands;

use Illuminate\Console\Command;

class SkeletonCommand extends Command
{
    protected $signature = 'skeleton:placeholder';

    protected $description = 'Placeholder Artisan command shipped by the package skeleton.';

    public function handle(): int
    {
        $this->line('Skeleton placeholder command executed.');

        return self::SUCCESS;
    }
}
