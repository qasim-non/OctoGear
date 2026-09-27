<?php

namespace App\Console\Commands;

use App\Services\ImageStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupImages extends Command
{
    protected $signature = 'images:cleanup';

    protected $description = 'Retry private image deletions that could not finish after replacement or rollback';

    public function handle(ImageStorageService $images): int
    {
        $deleted = $images->retryPendingDeletions();
        $remaining = DB::table('pending_image_deletions')->count();
        $this->info("Deleted {$deleted} pending image file(s).");

        if ($remaining > 0) {
            $this->warn("{$remaining} image deletion(s) remain pending.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
