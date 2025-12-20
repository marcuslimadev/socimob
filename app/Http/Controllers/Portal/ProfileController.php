<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * GET /api/portal/profile
     */
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario nao autenticado'], 401);
        }

        $tenantId = $request->attributes->get('tenant_id');
        $targetUser = $this->resolveTargetUser($request, $tenantId, $user);
        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'Usuario nao encontrado'], 404);
        }

        $lead = Lead::where('tenant_id', $tenantId)
            ->where('user_id', $targetUser->id)
            ->first();

        return response()->json([
            'success' => true,
            'user' => $targetUser,
            'lead' => $lead,
        ]);
    }

    /**
     * PUT /api/portal/profile
     */
    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario nao autenticado'], 401);
        }

        $tenantId = $request->attributes->get('tenant_id');
        $targetUser = $this->resolveTargetUser($request, $tenantId, $user);
        if (!$targetUser) {
            return response()->json(['success' => false, 'message' => 'Usuario nao encontrado'], 404);
        }

        $data = $this->validate($request, [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:50',
            'whatsapp' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
            'budget_min' => 'nullable|numeric',
            'budget_max' => 'nullable|numeric',
            'localizacao' => 'nullable|string|max:255',
            'quartos' => 'nullable|integer',
            'suites' => 'nullable|integer',
            'garagem' => 'nullable|integer',
            'caracteristicas_desejadas' => 'nullable|string',
            'observacoes_cliente' => 'nullable|string',
        ]);

        if (!empty($data['email']) && $data['email'] !== $targetUser->email) {
            $emailExists = User::where('email', $data['email'])
                ->where('id', '!=', $targetUser->id)
                ->exists();
            if ($emailExists) {
                return response()->json(['success' => false, 'message' => 'Email ja em uso'], 409);
            }
        }

        $userPayload = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value) => $value !== null);

        if (!empty($data['password'])) {
            $userPayload['password'] = Hash::make($data['password']);
        }

        if (!empty($userPayload)) {
            $targetUser->update($userPayload);
        }

        $lead = Lead::where('tenant_id', $tenantId)
            ->where('user_id', $targetUser->id)
            ->first();

        if (!$lead) {
            $lead = Lead::create([
                'tenant_id' => $tenantId,
                'nome' => $targetUser->name,
                'email' => $targetUser->email,
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'status' => 'novo',
                'user_id' => $targetUser->id,
                'primeira_interacao' => now(),
                'ultima_interacao' => now(),
            ]);
        }

        $leadPayload = array_filter([
            'nome' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'budget_min' => $data['budget_min'] ?? null,
            'budget_max' => $data['budget_max'] ?? null,
            'localizacao' => $data['localizacao'] ?? null,
            'quartos' => $data['quartos'] ?? null,
            'suites' => $data['suites'] ?? null,
            'garagem' => $data['garagem'] ?? null,
            'caracteristicas_desejadas' => $data['caracteristicas_desejadas'] ?? null,
            'observacoes_cliente' => $data['observacoes_cliente'] ?? null,
            'ultima_interacao' => now(),
        ], fn ($value) => $value !== null);

        if (!empty($leadPayload)) {
            $lead->update($leadPayload);
        }

        return response()->json([
            'success' => true,
            'message' => 'Perfil atualizado com sucesso',
            'user' => $targetUser->fresh(),
            'lead' => $lead->fresh(),
        ]);
    }

    private function resolveTargetUser(Request $request, ?int $tenantId, User $user): ?User
    {
        if ($user->role === 'client') {
            return $user;
        }

        $targetId = $request->query('user_id') ?: $request->input('user_id');
        if (!$targetId) {
            return $user;
        }

        return User::where('tenant_id', $tenantId)
            ->where('id', $targetId)
            ->first();
    }
}
