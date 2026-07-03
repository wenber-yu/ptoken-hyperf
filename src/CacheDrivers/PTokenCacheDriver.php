<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\CacheDrivers;

use Wenbo\PToken\CacheDrivers\PTokenCacheInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;

class PTokenCacheDriver implements PTokenCacheInterface
{
    private PsrCacheInterface $cache;

    public function __construct(PsrCacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
}
