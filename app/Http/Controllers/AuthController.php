<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\PaymentGatewayResolver;

/**
 * Controller de Autenticação
 */
class AuthController extends Controller
{
    public function __construct(private PaymentGatewayResolver $gatewayResolver)
    {
    }

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

        $tenant = $user->tenant;
        $requiresSubscription = $user->isAdmin() && (!$tenant || !$tenant->isSubscribed());
        $subscriptionAmount = $this->getHalfMinimumWage();
        $gateway = $this->gatewayResolver->getActiveGateway();

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tipo' => $user->role === 'super_admin' ? 'Super Admin' : ucfirst($user->role),
                'tenant_id' => $tenant?->id,
                'tenant_subscription_status' => $tenant?->subscription_status,
                'requires_subscription' => $requiresSubscription,
                'subscription_plan_amount' => $subscriptionAmount,
                'subscription_contract' => $this->subscriptionContractTerms($subscriptionAmount, $gateway),
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
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ]
        ]);
    }
    
    /**
     * Login com Google OAuth
     * POST /api/auth/google
     */
    public function googleLogin(Request $request)
    {
        try {
            $googleToken = $request->input('token');
            
            if (!$googleToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token do Google não fornecido'
                ], 400);
            }

            // Verificar o token do Google (simulado - implementar verificação real em produção)
            // Em produção, usar: https://developers.google.com/identity/sign-in/web/backend-auth
            
            // Decodificar o JWT do Google (simulado)
            $googleClientId = env('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
            
            // Por enquanto, vamos aceitar qualquer token e criar/buscar usuário
            // IMPORTANTE: Em produção, SEMPRE validar o token com a API do Google!
            
            // Simular dados extraídos do token Google
            $googleData = [
                'email' => 'usuario@gmail.com',  // Em produção, extrair do token
                'name' => 'Usuário Google',       // Em produção, extrair do token
                'google_id' => 'google_' . time() // Em produção, extrair do token
            ];

            // Buscar ou criar usuário
            $user = User::where('email', $googleData['email'])->first();
            
            if (!$user) {
                // Criar novo usuário como cliente
                $user = User::create([
                    'name' => $googleData['name'],
                    'email' => $googleData['email'],
                    'password' => Hash::make(uniqid()), // Senha aleatória (não será usada)
                    'role' => 'client',
                    'is_active' => 1,
                    'google_id' => $googleData['google_id']
                ]);
            }

            // Gerar token
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
                    'tipo' => $user->role === 'client' ? 'Cliente' : ucfirst($user->role),
                ],
                'message' => 'Login com Google realizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer login com Google',
                'error' => $e->getMessage()
            ], 500);
        }
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

    private function getHalfMinimumWage(): float
    {
        $minimum = (float) env('MINIMUM_WAGE_BRL', 1412.00);
        return round($minimum * 0.5, 2);
    }

    private function subscriptionContractTerms(float $amount, string $gateway): string
    {
        $gatewayLabel = $this->gatewayResolver->getGatewayLabel($gateway);

        return sprintf(
            'Cobrança recorrente mensal de 50%% do salário mínimo vigente (R$ %.2f) via %s, debitada automaticamente na conta configurada do titular da imobiliária.',
            $amount,
            $gatewayLabel
        );
    }
}
