<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use App\Services\LeadService;

class ClientAuthController extends Controller
{
    /**
     * POST /api/portal/auth/register
     */
    public function register(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        // DEBUG: Log tenant resolution for registration
        \Log::info('ClientAuthController::register tenant check', [
            'tenant_id_from_attributes' => $tenantId,
            'app_bound_tenant' => app()->bound('tenant'),
            'host' => $request->getHost(),
        ]);
        
        if (!$tenantId) {
            // Try to get from app container if middleware set it
            if (app()->bound('tenant')) {
                $tenant = app('tenant');
                $tenantId = $tenant->id;
                $request->attributes->set('tenant_id', $tenantId);
                \Log::info('ClientAuthController::register recovered tenant from app', ['tenant_id' => $tenantId]);
            } else {
                \Log::error('ClientAuthController::register - No tenant found', [
                    'host' => $request->getHost(),
                    'attributes' => $request->attributes->all(),
                ]);
                return response()->json(['error' => 'Tenant not found'], 404);
            }
        }

        // ✅ Validação aprimorada com password confirmation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|regex:/^[\pL\s\-]+$/u',
            'email' => 'required|email:rfc,dns|max:255',
            'password' => 'required|string|min:6|max:255|confirmed',
            'telefone' => 'nullable|string|max:20|regex:/^[\d\s\(\)\-\+]+$/',
        ], [
            'name.regex' => 'Nome deve conter apenas letras',
            'email.email' => 'Email deve ser válido',
            'password.min' => 'Senha deve ter no mínimo 6 caracteres',
            'password.confirmed' => 'Confirmação de senha não confere',
            'telefone.regex' => 'Telefone deve conter apenas números',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $existing = User::where('email', $data['email'])->first();
        if ($existing) {
            if ((int) $existing->tenant_id !== (int) $tenantId) {
                return response()->json(['success' => false, 'message' => 'Email ja cadastrado em outro tenant.'], 409);
            }
            return response()->json(['success' => false, 'message' => 'Email ja cadastrado.'], 409);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'client',
            'is_active' => 1,
            'tenant_id' => $tenantId,
        ]);

        $lead = $this->findOrCreateLead($tenantId, $user, $data['telefone'] ?? null);

        return $this->buildAuthResponse($user, $lead, 'Cadastro realizado com sucesso!');
    }

    /**
     * POST /api/portal/auth/login
     */
    public function login(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        
        // DEBUG: Log tenant resolution for login
        \Log::info('ClientAuthController::login tenant check', [
            'tenant_id_from_attributes' => $tenantId,
            'app_bound_tenant' => app()->bound('tenant'),
            'host' => $request->getHost(),
        ]);
        
        if (!$tenantId) {
            // Try to get from app container if middleware set it
            if (app()->bound('tenant')) {
                $tenant = app('tenant');
                $tenantId = $tenant->id;
                $request->attributes->set('tenant_id', $tenantId);
                \Log::info('ClientAuthController::login recovered tenant from app', ['tenant_id' => $tenantId]);
            } else {
                \Log::error('ClientAuthController::login - No tenant found', [
                    'host' => $request->getHost(),
                    'attributes' => $request->attributes->all(),
                ]);
                return response()->json(['error' => 'Tenant not found'], 404);
            }
        }

        // ✅ Validação de inputs
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'sometimes|string|min:6|max:255',
            'senha' => 'sometimes|string|min:6|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $email = $request->input('email');
        $password = $request->input('password') ?: $request->input('senha');

        if (!$password) {
            return response()->json(['success' => false, 'message' => 'Senha é obrigatória'], 400);
        }

        $user = User::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Credenciais invalidas'], 401);
        }

        $lead = $this->findOrCreateLead($tenantId, $user, null);

        return $this->buildAuthResponse($user, $lead, 'Login realizado com sucesso!');
    }

    /**
     * GET /api/portal/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario nao autenticado'], 401);
        }

        $lead = Lead::where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success' => true,
            'user' => $user,
            'lead' => $lead,
        ]);
    }

    private function buildAuthResponse(User $user, ?Lead $lead, string $message)
    {
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
                'tenant_id' => $user->tenant_id,
            ],
            'lead' => $lead,
            'message' => $message,
        ]);
    }

    private function findOrCreateLead(int $tenantId, User $user, ?string $telefone): ?Lead
    {
        /** @var LeadService $leadService */
        $leadService = app(LeadService::class);

        $lead = $leadService->findExisting($tenantId, $user->email, $telefone, $telefone);

        if ($lead) {
            return $leadService->saveUnique([
                'id' => $lead->id,
                'tenant_id' => $lead->tenant_id ?: $tenantId,
                'nome' => $user->name,
                'email' => $user->email,
                'telefone' => $telefone ?: $lead->telefone,
                'whatsapp' => $telefone ?: $lead->whatsapp,
                'user_id' => $user->id,
                'ultima_interacao' => Carbon::now(),
            ]);
        }

        return $leadService->saveUnique([
            'tenant_id' => $tenantId,
            'nome' => $user->name,
            'email' => $user->email,
            'telefone' => $telefone ?: null,
            'whatsapp' => $telefone ?: null,
            'status' => 'novo',
            'user_id' => $user->id,
            'primeira_interacao' => Carbon::now(),
            'ultima_interacao' => Carbon::now(),
        ]);
    }
}


