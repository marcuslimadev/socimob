<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $data = $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'telefone' => 'nullable|string|max:50',
        ]);

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
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $email = $request->input('email');
        $password = $request->input('password') ?: $request->input('senha');

        if (!$email || !$password) {
            return response()->json(['success' => false, 'message' => 'Email e senha sao obrigatorios'], 400);
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


