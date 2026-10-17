<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Console;

use ForgeMesh\PayPerCrawl\Laravel\Models\Hit;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'pay-per-crawl:prune {--days= : Override config retention_days}';

    protected $description = 'Delete Pay-per-Crawl hit rows older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('pay-per-crawl.retention_days', 90)));
        $n = Hit::query()->where('ts', '<', gmdate('Y-m-d H:i:s', time() - $days * 86400))->delete();
        $this->info("Deleted {$n} hit(s) older than {$days} day(s).");
        return self::SUCCESS;
    }
}
