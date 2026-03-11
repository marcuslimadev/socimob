<?php

namespace App\Http\Controllers\Portal;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Autenticação do Portal do Proprietário
 * Permite que proprietários (Pessoas com papel 'proprietario') façam login
 * usando email + CPF (ou apenas email).
 */
class ProprietarioAuthController
{
    /**
     * POST /api/portal/proprietario/auth/login
     * Body: { email, cpf_cnpj? }
     */
    public function login(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não identificado'], 400);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $cpfCnpj = $request->input('cpf_cnpj') ?: $request->input('cpf');

        // Encontrar Pessoa pelo email no tenant
        $query = Pessoa::where('tenant_id', $tenantId)
            ->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
            });

        // Se CPF/CNPJ fornecido, usar como segundo fator
        if ($cpfCnpj) {
            $cpfLimpo = preg_replace('/\D/', '', $cpfCnpj);
            $query->where(function ($q) use ($cpfLimpo) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(cpf, '.',''),'-',''),'/', '') = ?", [$cpfLimpo])
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.',''),'-',''),'/', '') = ?", [$cpfLimpo]);
            });
        }

        $pessoa = $query->first();

        if (!$pessoa) {
            return response()->json([
                'success' => false,
                'message' => 'Não encontramos nenhum proprietário com os dados informados.',
            ], 401);
        }

        // Verificar se a pessoa tem papel de proprietário
        $papeis = $pessoa->papeis ?? [];
        if (is_string($papeis)) {
            $papeis = json_decode($papeis, true) ?? [];
        }

        if (!in_array('proprietario', $papeis)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta conta não possui acesso ao Portal do Proprietário.',
            ], 403);
        }

        // Encontrar ou criar User vinculado a esta Pessoa
        $user = User::where('email', $email)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$user) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name'      => $pessoa->nome,
                'email'     => $email,
                'password'  => Hash::make(bin2hex(random_bytes(16))),
                'role'      => 'proprietario',
                'is_active' => 1,
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Conta inativa. Entre em contato com a imobiliária.',
            ], 403);
        }

        $secret = config('app.key', env('JWT_SECRET', 'default-secret-key'));
        $token = base64_encode($user->id . '|' . time() . '|' . $secret);

        Log::info('ProprietarioAuthController::login success', [
            'pessoa_id' => $pessoa->id,
            'user_id'   => $user->id,
            'tenant_id' => $tenantId,
        ]);

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'pessoa_id' => $pessoa->id,
                'role'      => 'proprietario',
            ],
        ]);
    }
}
