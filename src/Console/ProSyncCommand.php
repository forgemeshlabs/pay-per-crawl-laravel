<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Console;

use ForgeMesh\PayPerCrawl\Contracts\HitStoreInterface;
use ForgeMesh\PayPerCrawl\Gate;
use ForgeMesh\PayPerCrawl\NativeHttpClient;
use ForgeMesh\PayPerCrawl\Pro;
use ForgeMesh\PayPerCrawl\Laravel\PayPerCrawlServiceProvider;
use Illuminate\Console\Command;

class ProSyncCommand extends Command
{
    protected $signature = 'pay-per-crawl:pro-sync';

    protected $description = 'Sync rollups to the optional ForgeMesh Pro dashboard (no-op without a license key)';

    public function handle(Gate $gate, HitStoreInterface $store): int
    {
        $settings = $gate->settings();
        if (!Pro::enabled($settings)) {
            $this->line('No Pro license key configured; nothing to do.');
            return self::SUCCESS;
        }
        $url = (string) ($settings['site_url'] ?: config('app.url'));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $cache = app('pay-per-crawl.cache');
        $pro = new Pro($settings, new NativeHttpClient(PayPerCrawlServiceProvider::UA), $host, rtrim($url, '/') . '/', 'laravel');
        $state = $pro->sync($store, (array) $cache->get('ppc:pro_state', []), (string) ($settings['site_name'] ?: $host));
        $cache->forever('ppc:pro_state', $state);
        $this->line(sprintf('license=%s sync=%s%s', !empty($state['ok']) ? 'ok' : 'invalid', !empty($state['sync_ok']) ? 'ok' : 'failed', !empty($state['sync_error']) ? ' (' . $state['sync_error'] . ')' : ''));
        return !empty($state['ok']) && !empty($state['sync_ok']) ? self::SUCCESS : self::FAILURE;
    }
}
