<?php

namespace ForgeMesh\PayPerCrawl\Laravel;

use ForgeMesh\PayPerCrawl\Classifier;
use ForgeMesh\PayPerCrawl\Contracts\HitStoreInterface;
use ForgeMesh\PayPerCrawl\Gate;
use ForgeMesh\PayPerCrawl\NativeHttpClient;
use ForgeMesh\PayPerCrawl\Pro;
use ForgeMesh\PayPerCrawl\Laravel\Console\ProSyncCommand;
use ForgeMesh\PayPerCrawl\Laravel\Console\PruneCommand;
use ForgeMesh\PayPerCrawl\Laravel\Console\StatsCommand;
use ForgeMesh\PayPerCrawl\Laravel\Http\Middleware\PayPerCrawl;
use ForgeMesh\PayPerCrawl\Laravel\Support\EloquentHitStore;
use ForgeMesh\PayPerCrawl\Laravel\Support\LaravelCache;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class PayPerCrawlServiceProvider extends ServiceProvider
{
    public const VERSION = '0.1.0';
    public const UA = 'forgemesh-pay-per-crawl-laravel/' . self::VERSION;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/pay-per-crawl.php', 'pay-per-crawl');

        $this->app->singleton('pay-per-crawl.cache', function ($app): Repository {
            $store = $app['config']->get('pay-per-crawl.cache_store');
            return $store ? Cache::store($store) : Cache::store();
        });

        $this->app->singleton(HitStoreInterface::class, EloquentHitStore::class);

        $this->app->singleton(Gate::class, function ($app): Gate {
            $settings = self::settings($app['config']->get('pay-per-crawl'), (string) $app['config']->get('app.key'));
            $classifier = null;
            if (Pro::enabled($settings)) {
                $state = $app['pay-per-crawl.cache']->get('ppc:pro_state', []);
                $agents = $state['intel']['agents'] ?? [];
                $classifier = new Classifier(new LaravelCache($app['pay-per-crawl.cache']), $agents ? Classifier::mergeCatalog(Classifier::shipped(), $agents) : null);
            }
            return new Gate(
                $settings,
                new LaravelCache($app['pay-per-crawl.cache']),
                new NativeHttpClient(self::UA),
                $app->make(HitStoreInterface::class),
                $classifier,
                'forgemesh-pay-per-crawl-laravel'
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $router = $this->app['router'];
        $router->aliasMiddleware('pay-per-crawl', PayPerCrawl::class);
        // After every provider has booted: some (Sanctum) re-sync the kernel's middleware groups
        // from their own definitions and would silently drop a push made earlier.
        $this->app->booted(function () use ($router): void {
            foreach ((array) config('pay-per-crawl.middleware_groups', []) as $group) {
                $router->pushMiddlewareToGroup($group, PayPerCrawl::class);
            }
        });

        if (config('pay-per-crawl.discovery')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/pay-per-crawl.php');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/pay-per-crawl.php' => config_path('pay-per-crawl.php')], 'pay-per-crawl-config');
            $this->publishes([__DIR__ . '/../database/migrations' => database_path('migrations')], 'pay-per-crawl-migrations');
            $this->commands([StatsCommand::class, PruneCommand::class, ProSyncCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('pay-per-crawl:prune')->daily();
            if (Pro::enabled((array) config('pay-per-crawl'))) {
                $schedule->command('pay-per-crawl:pro-sync')->hourly();
            }
        });
    }

    /**
     * Config plus the framework-derived values the core needs.
     *
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function settings(array $config, string $appKey): array
    {
        if (($config['mpp_secret'] ?? '') === '') {
            $config['mpp_secret'] = hash_hmac('sha256', 'pay-per-crawl:mpp', $appKey);
        }
        if (($config['ip_salt'] ?? '') === '') {
            $config['ip_salt'] = hash_hmac('sha256', 'pay-per-crawl:ip', $appKey);
        }
        return $config;
    }
}
