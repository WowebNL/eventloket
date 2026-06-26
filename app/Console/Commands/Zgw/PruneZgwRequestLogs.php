<?php

namespace App\Console\Commands\Zgw;

use App\Models\ZgwRequestLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zgw:prune-request-logs {--days=90 : Delete request logs older than this many days}')]
#[Description('Delete ZGW request logs older than the retention window.')]
class PruneZgwRequestLogs extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = ZgwRequestLog::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ZGW request log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
