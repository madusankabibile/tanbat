<?php

namespace App\Console\Commands;

use App\Services\AffinityCalculator;
use Illuminate\Console\Command;

class BackfillUserAffinity extends Command
{
    protected $signature   = 'feed:backfill-affinity {--user= : Rebuild only this user id}';
    protected $description = 'Rebuild user_affinity scores from existing likes / comments / shares / follows / interactions.';

    public function handle(AffinityCalculator $calc): int
    {
        $userId = $this->option('user');
        if ($userId) {
            $this->info("Rebuilding affinity for user #{$userId}…");
            $calc->rebuildForUser((int) $userId);
            $this->info('Done.');
            return self::SUCCESS;
        }

        $this->info('Rebuilding affinity for all users…');
        $count = $calc->rebuildAll();
        $this->info("Rebuilt {$count} users.");
        return self::SUCCESS;
    }
}
