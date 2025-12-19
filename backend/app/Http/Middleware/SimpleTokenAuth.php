<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class SimpleTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || stripos($authHeader, 'bearer ') !== 0) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = trim(substr($authHeader, 7));
        $decoded = base64_decode($token, true);
        if (!$decoded || !str_contains($decoded, '|')) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        [$userId] = explode('|', $decoded, 3);
        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Injeta o usuário na request para os controllers
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
