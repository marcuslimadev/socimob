<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de Rate Limiting
 * Limita requisições por IP e por usuário autenticado
 */
class RateLimitMiddleware
{
    /**
     * Limites padrão
     */
    private $limits = [
        'ip' => [
            'max_requests' => 60,
            'window_seconds' => 60,
        ],
        'user' => [
            'max_requests' => 120,
            'window_seconds' => 60,
        ],
        'api' => [
            'max_requests' => 1000,
            'window_seconds' => 3600,
        ]
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $limitType
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $limitType = null)
    {
        // Verificar se rate limiting está habilitado
        if (!env('RATE_LIMIT_ENABLED', true)) {
            return $next($request);
        }

        $ip = $request->ip();
        $user = $request->user();
        $userId = $user ? $user->id : null;

        // Determinar tipo de limite
        $limitConfig = $this->getLimitConfig($limitType);

        // Rate limit por IP
        $ipKey = "rate_limit:ip:{$ip}";
        $ipLimited = $this->checkLimit($ipKey, $limitConfig['max_requests'], $limitConfig['window_seconds']);

        if ($ipLimited) {
            Log::warning('Rate limit exceeded by IP', [
                'ip' => $ip,
                'path' => $request->path(),
                'method' => $request->method()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $this->getRetryAfter($ipKey, $limitConfig['window_seconds'])
            ], 429);
        }

        // Rate limit por usuário (se autenticado)
        if ($userId) {
            $userKey = "rate_limit:user:{$userId}";
            $userLimited = $this->checkLimit($userKey, $limitConfig['max_requests'] * 2, $limitConfig['window_seconds']);

            if ($userLimited) {
                Log::warning('Rate limit exceeded by user', [
                    'user_id' => $userId,
                    'ip' => $ip,
                    'path' => $request->path()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $this->getRetryAfter($userKey, $limitConfig['window_seconds'])
                ], 429);
            }
        }

        // Incrementar contadores
        $this->incrementCounter($ipKey, $limitConfig['window_seconds']);
        if ($userId) {
            $this->incrementCounter("rate_limit:user:{$userId}", $limitConfig['window_seconds']);
        }

        // Adicionar headers de rate limit
        $response = $next($request);

        $remaining = $this->getRemaining($ipKey, $limitConfig['max_requests']);
        $resetTime = Cache::get("{$ipKey}:reset", time() + $limitConfig['window_seconds']);

        if (method_exists($response, 'header')) {
            $response->header('X-RateLimit-Limit', $limitConfig['max_requests']);
            $response->header('X-RateLimit-Remaining', max(0, $remaining));
            $response->header('X-RateLimit-Reset', $resetTime);
        }

        return $response;
    }

    /**
     * Verificar se limite foi excedido
     *
     * @param string $key
     * @param int $maxRequests
     * @param int $windowSeconds
     * @return bool
     */
    private function checkLimit(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $count = Cache::get($key, 0);
        return $count >= $maxRequests;
    }

    /**
     * Incrementar contador
     *
     * @param string $key
     * @param int $windowSeconds
     * @return void
     */
    private function incrementCounter(string $key, int $windowSeconds): void
    {
        $count = Cache::get($key, 0);

        if ($count === 0) {
            // Primeiro request, definir reset time
            Cache::put("{$key}:reset", time() + $windowSeconds, $windowSeconds);
        }

        Cache::put($key, $count + 1, $windowSeconds);
    }

    /**
     * Obter requests restantes
     *
     * @param string $key
     * @param int $maxRequests
     * @return int
     */
    private function getRemaining(string $key, int $maxRequests): int
    {
        $count = Cache::get($key, 0);
        return $maxRequests - $count;
    }

    /**
     * Obter tempo até reset (em segundos)
     *
     * @param string $key
     * @param int $windowSeconds
     * @return int
     */
    private function getRetryAfter(string $key, int $windowSeconds): int
    {
        $resetTime = Cache::get("{$key}:reset", time() + $windowSeconds);
        return max(0, $resetTime - time());
    }

    /**
     * Obter configuração de limite baseado no tipo
     *
     * @param string|null $limitType
     * @return array
     */
    private function getLimitConfig(?string $limitType): array
    {
        if ($limitType && isset($this->limits[$limitType])) {
            return $this->limits[$limitType];
        }

        // Padrão: limite por IP
        return $this->limits['ip'];
    }

    /**
     * Limpar rate limits de um usuário ou IP (útil para testes/admin)
     *
     * @param string $identifier
     * @param string $type ('ip' ou 'user')
     * @return void
     */
    public static function clearLimit(string $identifier, string $type = 'ip'): void
    {
        $key = "rate_limit:{$type}:{$identifier}";
        Cache::forget($key);
        Cache::forget("{$key}:reset");

        Log::info('Rate limit cleared', [
            'type' => $type,
            'identifier' => $identifier
        ]);
    }
}
