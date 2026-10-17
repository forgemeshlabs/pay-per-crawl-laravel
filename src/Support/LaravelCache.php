<?php

namespace ForgeMesh\PayPerCrawl\Laravel\Support;

use ForgeMesh\PayPerCrawl\Contracts\CacheInterface;
use Illuminate\Contracts\Cache\Repository;

final class LaravelCache implements CacheInterface
{
    public function __construct(private Repository $store)
    {
    }

    public function get(string $key): mixed
    {
        return $this->store->get('ppc:' . $key);
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->store->put('ppc:' . $key, $value, $ttlSeconds);
    }

    public function add(string $key, mixed $value, int $ttlSeconds): bool
    {
        return $this->store->add('ppc:' . $key, $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        $this->store->forget('ppc:' . $key);
    }
}
