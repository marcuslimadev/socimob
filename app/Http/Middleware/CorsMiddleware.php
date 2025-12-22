<?php

namespace App\Http\Middleware;

use Closure;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $allowedOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*'))));
        $requestOrigin = $request->headers->get('Origin');
        $originIsWildcard = empty($allowedOrigins) || $allowedOrigins[0] === '*';
        $resolvedOrigin = $originIsWildcard ? '*' : ($requestOrigin && in_array($requestOrigin, $allowedOrigins) ? $requestOrigin : $allowedOrigins[0]);

        $headers = [
            'Access-Control-Allow-Origin'      => $resolvedOrigin,
            'Access-Control-Allow-Methods'     => 'POST, GET, OPTIONS, PUT, DELETE, PATCH',
            'Access-Control-Allow-Credentials' => $originIsWildcard ? 'false' : 'true',
            'Access-Control-Max-Age'           => '86400',
            'Access-Control-Allow-Headers'     => 'Content-Type, Authorization, X-Requested-With'
        ];

        if ($request->isMethod('OPTIONS')) {
            return response()->json(['method' => 'OPTIONS'], 200, $headers);
        }

        $response = $next($request);
        
        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
}
