<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Controller de Autenticação
 */
class AuthController extends Controller
{
    /**
     * Login
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'senha' => 'required'
        ]);
        
        $user = User::where('email', $request->email)
            ->where('ativo', 1)
            ->first();
        
        if (!$user || !Hash::check($request->senha, $user->senha)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas'
            ], 401);
        }
        
        // Gerar token JWT simples (em produção usar pacote JWT)
        $secret = env('JWT_SECRET', env('APP_KEY', 'default-secret-key-change-in-production'));
        $token = base64_encode($user->id . '|' . time() . '|' . $secret);
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'tipo' => $user->tipo
            ]
        ]);
    }
    
    /**
     * Obter usuário autenticado
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user; // Definido pelo middleware
        
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'nome' => $user->nome,
                'email' => $user->email,
                'tipo' => $user->tipo,
                'telefone' => $user->telefone
            ]
        ]);
    }
    
    /**
     * Logout
     * POST /api/auth/logout
     */
    public function logout()
    {
        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ]);
    }
}
