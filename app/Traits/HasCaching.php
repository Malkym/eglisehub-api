<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCaching
{
    protected function cacheResponse(string $key, callable $callback, int $ttl = 300): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    protected function invalidateCache(string $pattern): void
    {
        Cache::flush();
    }

    protected function cacheKey(string $prefix, string $identifier): string
    {
        return "{$prefix}:{$identifier}";
    }
}