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
        $email = $request->input('email');
        $password = $request->input('password') ?: $request->input('senha');
        
        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Email e senha são obrigatórios'
            ], 400);
        }
        
        $user = User::where('email', $email)
            ->where('is_active', 1)
            ->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas'
            ], 401);
        }
        
        // Gerar token simples
        $secret = env('JWT_SECRET', env('APP_KEY', 'default-secret-key'));
        $token = base64_encode($user->id . '|' . time() . '|' . $secret);
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tipo' => $user->role === 'super_admin' ? 'Super Admin' : ucfirst($user->role),
            ],
            'message' => 'Login realizado com sucesso!'
        ]);
    }
    
    /**
     * Obter usuário autenticado
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user; // Definido pelo middleware
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
        }
        
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
