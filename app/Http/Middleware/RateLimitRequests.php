<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    protected int $maxAttempts = 60;
    protected int $decaySeconds = 60;

    public function handle(Request $request, Closure $next, ?int $maxAttempts = null, ?int $decaySeconds = null): Response
    {
        $maxAttempts = $maxAttempts ?? $this->maxAttempts;
        $decaySeconds = $decaySeconds ?? $this->decaySeconds;

        $key = $this->resolveRequestSignature($request);

        if ($this->hasTooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes. Veuillez réessayer plus tard.',
                'retry_after' => $this->availableIn($key),
            ], 429);
        }

        $this->hit($key, $decaySeconds);

        return $next($request);
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip() ?? 'unknown';
        
        return sha1($request->fullUrl() . '|' . $userId . '|' . $ip);
    }

    protected function hasTooManyAttempts(string $key, int $maxAttempts): bool
    {
        $keyAttempts = cache()->get('rate_limit:' . $key, 0);
        return $keyAttempts >= $maxAttempts;
    }

    protected function hit(string $key, int $decaySeconds): void
    {
        $keyAttempts = cache()->get('rate_limit:' . $key, 0);
        cache()->put('rate_limit:' . $key, $keyAttempts + 1, $decaySeconds);
    }

    protected function availableIn(string $key): int
    {
        return cache()->ttl('rate_limit:' . $key) ?? 60;
    }
}