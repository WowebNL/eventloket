<?php

namespace App\Console\Commands\Zgw;

use App\Models\ZgwRequestLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zgw:prune-request-logs {--days= : Override the configured retention window (days)}')]
#[Description('Delete ZGW request logs older than the retention window.')]
class PruneZgwRequestLogs extends Command
{
    public function handle(): int
    {
        // Default to the configured retention policy; --days overrides it ad hoc.
        $days = max(1, (int) ($this->option('days') ?? config('zgw.request_log_retention_days', 90)));
        $cutoff = now()->subDays($days);

        $deleted = ZgwRequestLog::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ZGW request log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
