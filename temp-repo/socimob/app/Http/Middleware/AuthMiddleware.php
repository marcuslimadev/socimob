<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token não fornecido'
            ], 401);
        }
        
        // Decodificar token simples (em produção usar JWT)
        $decoded = base64_decode($token);
        $parts = explode('|', $decoded);
        
        if (count($parts) !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido'
            ], 401);
        }
        
        list($userId, $timestamp, $secret) = $parts;
        
        if ($secret !== env('JWT_SECRET')) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido'
            ], 401);
        }
        
        $user = User::where('id', $userId)->where('ativo', 1)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não encontrado'
            ], 401);
        }
        
        // Adicionar usuário ao request
        $request->user = $user;
        
        return $next($request);
    }
}
