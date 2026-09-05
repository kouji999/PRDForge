<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-user AI rate limiting. AI endpoints are expensive — throttle
 * generously but firmly. Keyed per user (auth) or IP (fallback).
 */
class ThrottleAiOperations
{
    /** [maxAttempts, decaySeconds] per operation class. */
    private const LIMITS = [
        'chat' => [60, 300],        // 12/min avg
        'extract' => [15, 300],     // 3/min
        'generate' => [5, 600],     // heavy: 5 per 10 min
        'action' => [20, 300],
        'review' => [10, 300],
        'test' => [10, 300],
    ];

    public function handle(Request $request, Closure $next, string $operation): Response
    {
        [$max, $decay] = self::LIMITS[$operation] ?? [30, 300];

        $key = sprintf(
            'ai:%s:%s',
            $operation,
            $request->user()?->id ?? $request->ip(),
        );

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retry = RateLimiter::availableIn($key);

            return response()->json([
                'error' => "Terlalu banyak permintaan. Coba lagi dalam {$retry} detik.",
                'category' => 'rate_limited',
                'retry_after' => $retry,
            ], 429);
        }

        RateLimiter::hit($key, $decay);

        return $next($request);
    }
}
