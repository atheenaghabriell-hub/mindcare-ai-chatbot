<?php

namespace App\Console\Commands;

use App\Models\CrisisReviewEvent;
use Illuminate\Console\Command;

class PurgeExpiredCrisisReviewEvents extends Command
{
    protected $signature = 'mindcare:purge-expired-crisis-reviews';

    protected $description = 'Delete crisis review metadata after its retention period';

    public function handle(): int
    {
        $deleted = CrisisReviewEvent::query()
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired crisis review event(s).");

        return self::SUCCESS;
    }
}
