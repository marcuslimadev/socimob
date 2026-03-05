<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;
use App\Services\DomainService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        // Parse JSON se Content-Type for application/json
        $data = $request->isJson() ? $request->json()->all() : $request->all();
        
        // Validação de inputs
        $validator = Validator::make($data, [
            'email' => 'required|email|max:255',
            'password' => 'sometimes|string|min:6|max:255',
            'senha' => 'sometimes|string|min:6|max:255',
        ], [
            'email.required' => 'Email é obrigatório',
            'email.email' => 'Email deve ser válido',
            'password.min' => 'Senha deve ter no mínimo 6 caracteres',
            'senha.min' => 'Senha deve ter no mínimo 6 caracteres',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? $data['senha'] ?? null;
        
        if (!$password) {
            return response()->json([
                'success' => false,
                'message' => 'Senha é obrigatória'
            ], 400);
        }
        
        $user = User::where('email', $email)
            ->where(function ($query) {
                $query->where('is_active', 1)
                    ->orWhereNull('is_active');
            })
            ->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas'
            ], 401);
        }
        
        // Validar tenant - usuários só podem fazer login no domínio do seu tenant
        // Super admins (sem tenant_id) podem fazer login em qualquer domínio
        if ($user->tenant_id) {
            $currentTenant = app()->bound('tenant')
                ? app('tenant')
                : $this->resolveTenantFromRequest($request);
            
            // CRITICAL DEBUG: Log tenant resolution
            Log::info('AuthController login tenant check', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_tenant_id' => $user->tenant_id,
                'app_bound_tenant' => app()->bound('tenant'),
                'current_tenant_id' => $currentTenant ? $currentTenant->id : null,
                'current_tenant_domain' => $currentTenant ? $currentTenant->domain : null,
                'host' => $request->getHost(),
                'tenant_id_from_attributes' => $request->attributes->get('tenant_id'),
            ]);

            if (!$currentTenant || $currentTenant->id !== $user->tenant_id) {
                Log::warning('Login tenant mismatch - BLOCKED', [
                    'user_id' => $user->id,
                    'user_tenant_id' => $user->tenant_id,
                    'current_tenant_id' => $currentTenant ? $currentTenant->id : null,
                    'host' => $request->getHost(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Este usuário não tem acesso a este domínio. Acesse pelo domínio correto da sua imobiliária.'
                ], 403);
            }
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

    private function resolveTenantFromRequest(Request $request): ?Tenant
    {
        // SECURITY: Only accept tenant from headers or host, NOT query parameters
        $tenantId = $request->header('X-Tenant-Id');
        if (!empty($tenantId)) {
            return Tenant::find($tenantId);
        }

        $domainService = app(DomainService::class);
        $tenantDomain = $request->header('X-Tenant-Domain');
        if (!empty($tenantDomain)) {
            return $domainService->findByDomain($tenantDomain);
        }

        $tenantSlug = $request->header('X-Tenant-Slug');
        if (!empty($tenantSlug)) {
            return Tenant::where('slug', $tenantSlug)->first();
        }

        $host = $request->getHost();
        if (!empty($host)) {
            return $domainService->findByDomain($host);
        }

        return null;
    }
    
    /**
     * Obter usuário autenticado
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user(); // Usar método user() para resolver via middleware
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
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
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|min:10',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token do Google é obrigatório',
                    'errors' => $validator->errors()
                ], 422);
            }

            $googleToken = $request->input('token');
            $googleClientId = env('GOOGLE_CLIENT_ID');

            if (!$googleClientId) {
                Log::error('GOOGLE_CLIENT_ID não configurado no .env');
                return response()->json([
                    'success' => false,
                    'message' => 'Login com Google não configurado neste servidor.',
                ], 503);
            }

            // Verificar o ID token com a API do Google
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $googleToken,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token do Google inválido ou expirado.',
                ], 401);
            }

            $payload = $response->json();

            // Garantir que o token foi emitido para o nosso app
            if (($payload['aud'] ?? '') !== $googleClientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token do Google não pertence a este aplicativo.',
                ], 401);
            }

            $googleData = [
                'email'     => $payload['email'] ?? null,
                'name'      => $payload['name'] ?? ($payload['email'] ?? 'Usuário Google'),
                'google_id' => $payload['sub'] ?? null,
            ];

            if (!$googleData['email'] || !$googleData['google_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível obter os dados do Google.',
                ], 401);
            }

            // Resolver tenant atual - obrigatório para Google Login
            $currentTenant = app()->bound('tenant') ? app('tenant') : null;
            if (!$currentTenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível identificar a imobiliária. Acesse pelo domínio correto.',
                ], 403);
            }

            // Buscar usuário por google_id ou email
            $user = User::where('google_id', $googleData['google_id'])->first()
                ?? User::where('email', $googleData['email'])->first();

            if (!$user) {
                // Criar novo usuário como cliente, vinculado ao tenant atual
                $user = User::create([
                    'name'       => $googleData['name'],
                    'email'      => $googleData['email'],
                    'password'   => Hash::make(bin2hex(random_bytes(16))),
                    'role'       => 'client',
                    'is_active'  => 1,
                    'google_id'  => $googleData['google_id'],
                    'tenant_id'  => $currentTenant->id,
                ]);
            } else {
                if ($user->tenant_id && $user->tenant_id !== $currentTenant->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este usuário não tem acesso a este domínio.',
                    ], 403);
                }

                // Associar google_id se ainda não estiver vinculado
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleData['google_id']]);
                }
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário inativo. Entre em contato com a imobiliária.',
                ], 403);
            }

            // Gerar token
            $secret = env('JWT_SECRET', env('APP_KEY', 'default-secret-key'));
            $token = base64_encode($user->id . '|' . time() . '|' . $secret);

            return response()->json([
                'success' => true,
                'token'   => $token,
                'user'    => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $user->role,
                    'tipo'  => $user->role === 'client' ? 'Cliente' : ucfirst($user->role),
                ],
                'message' => 'Login com Google realizado com sucesso!',
            ]);

        } catch (\Exception $e) {
            Log::error('Google login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer login com Google.',
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
}
