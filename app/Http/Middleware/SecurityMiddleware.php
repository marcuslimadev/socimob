<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de Segurança
 * Valida entrada, previne ataques comuns
 */
class SecurityMiddleware
{
    /**
     * Padrões de ataque conhecidos
     */
    private $sqlPatterns = [
        '/(\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\b)/i',
        '/--/',
        '/\/\*.*\*\//',
        '/;.*\s*(DROP|DELETE|INSERT|UPDATE)/i',
    ];

    private $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/javascript:/i',
        '/on\w+\s*=/i', // onclick, onerror, etc
        '/<object[^>]*>.*?<\/object>/is',
        '/<embed[^>]*>/i',
    ];

    private $pathTraversalPatterns = [
        '/\.\.\//',
        '/\.\.\\\\/',
        '/\.\.\%2[fF]/',
        '/\.\.\%5[cC]/',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Validar entrada contra SQL Injection
        if ($this->detectSQLInjection($request)) {
            Log::warning('Possible SQL Injection attempt detected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'input' => $request->except(['password', 'token'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid input detected'
            ], 400);
        }

        // Validar entrada contra XSS
        if ($this->detectXSS($request)) {
            Log::warning('Possible XSS attempt detected', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid input detected'
            ], 400);
        }

        // Validar contra Path Traversal
        if ($this->detectPathTraversal($request)) {
            Log::warning('Possible Path Traversal attempt detected', [
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid path detected'
            ], 400);
        }

        // Adicionar security headers
        $response = $next($request);

        if (method_exists($response, 'header')) {
            // Prevenir clickjacking
            $response->header('X-Frame-Options', 'SAMEORIGIN');

            // Prevenir MIME type sniffing
            $response->header('X-Content-Type-Options', 'nosniff');

            // Habilitar XSS protection no browser
            $response->header('X-XSS-Protection', '1; mode=block');

            // Content Security Policy
            $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");

            // Referrer Policy
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

            // Permissions Policy
            $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }

        return $response;
    }

    /**
     * Detectar tentativa de SQL Injection
     *
     * @param Request $request
     * @return bool
     */
    private function detectSQLInjection(Request $request): bool
    {
        $input = $this->getAllInput($request);

        foreach ($input as $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach ($this->sqlPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detectar tentativa de XSS
     *
     * @param Request $request
     * @return bool
     */
    private function detectXSS(Request $request): bool
    {
        $input = $this->getAllInput($request);

        foreach ($input as $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach ($this->xssPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Detectar tentativa de Path Traversal
     *
     * @param Request $request
     * @return bool
     */
    private function detectPathTraversal(Request $request): bool
    {
        $input = array_merge(
            $request->all(),
            [$request->path()],
            $request->segments()
        );

        foreach ($input as $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach ($this->pathTraversalPatterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obter todos os inputs da requisição
     *
     * @param Request $request
     * @return array
     */
    private function getAllInput(Request $request): array
    {
        $input = [];

        // Query parameters
        foreach ($request->query() as $key => $value) {
            $input[] = $value;
        }

        // Request body
        foreach ($request->all() as $key => $value) {
            if (is_array($value)) {
                $input = array_merge($input, $this->flattenArray($value));
            } else {
                $input[] = $value;
            }
        }

        // Headers suspeitos
        $suspiciousHeaders = ['User-Agent', 'Referer', 'X-Forwarded-For'];
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                $input[] = $request->header($header);
            }
        }

        return $input;
    }

    /**
     * Achatar array recursivamente
     *
     * @param array $array
     * @return array
     */
    private function flattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Sanitizar string contra XSS
     *
     * @param string $string
     * @return string
     */
    public static function sanitizeXSS(string $string): string
    {
        // Remove tags HTML
        $string = strip_tags($string);

        // Converte caracteres especiais
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        return $string;
    }

    /**
     * Sanitizar string contra SQL Injection
     *
     * @param string $string
     * @return string
     */
    public static function sanitizeSQL(string $string): string
    {
        // Escape caracteres especiais
        $string = addslashes($string);

        // Remove comandos SQL perigosos
        $dangerous = ['--', ';', '/*', '*/', 'xp_', 'sp_', 'exec', 'execute'];
        $string = str_ireplace($dangerous, '', $string);

        return $string;
    }

    /**
     * Validar email
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validar URL
     *
     * @param string $url
     * @return bool
     */
    public static function isValidURL(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validar IP
     *
     * @param string $ip
     * @return bool
     */
    public static function isValidIP(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Limpar path de caracteres perigosos
     *
     * @param string $path
     * @return string
     */
    public static function sanitizePath(string $path): string
    {
        // Remove ../ e variações
        $path = preg_replace('/\.\.+[\/\\\\]/', '', $path);

        // Remove caracteres nulos
        $path = str_replace(chr(0), '', $path);

        // Normalizar separadores
        $path = str_replace('\\', '/', $path);

        return $path;
    }
}
