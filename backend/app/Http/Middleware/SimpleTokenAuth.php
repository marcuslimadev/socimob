<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimpleTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || stripos($authHeader, 'bearer ') !== 0) {
            Log::warning('SimpleTokenAuth: Missing or invalid Authorization header');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = trim(substr($authHeader, 7));
        $decoded = base64_decode($token, true);
        if (!$decoded || !str_contains($decoded, '|')) {
            Log::warning('SimpleTokenAuth: Invalid token format', ['token' => substr($token, 0, 20)]);
            return response()->json(['error' => 'Invalid token'], 401);
        }

        [$userId] = explode('|', $decoded, 3);
        $user = User::with('tenant')->find($userId);
        
        if (!$user) {
            Log::warning('SimpleTokenAuth: User not found', ['userId' => $userId]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info('SimpleTokenAuth: User authenticated', [
            'userId' => $user->id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        // Injeta o usuário na request para os controllers
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
