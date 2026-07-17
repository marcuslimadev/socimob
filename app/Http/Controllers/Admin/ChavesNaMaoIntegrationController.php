<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;

class ChavesNaMaoIntegrationController extends Controller
{
    public function show(Request $request)
    {
        $tenant = $this->tenantFor($request);
        if ($tenant instanceof \Illuminate\Http\JsonResponse) {
            return $tenant;
        }

        $domain = preg_replace('#^https?://#i', '', (string) $tenant->domain);
        $domain = trim((string) $domain, '/');
        $baseUrl = 'https://' . $domain;

        return response()->json([
            'platform_name' => 'SOCIMOB',
            'platform_site' => 'https://socimob.com',
            'platform_logo_url' => 'https://socimob.com/assets/logo-socimob.svg',
            'client_name' => $tenant->name,
            'client_email' => $tenant->getIntegrationValue('chaves_na_mao_email'),
            'token_configured' => !empty($tenant->metadata['chaves_na_mao_token_encrypted'])
                || (bool) $tenant->getIntegrationValue('chaves_na_mao_token')
                || ($tenant->id === 1 && (bool) env('EXCLUSIVA_CHAVES_NA_MAO')),
            'xml_url' => $baseUrl . '/integracoes/chaves-na-mao/imoveis.xml',
            'leads_url' => $baseUrl . '/webhook/chaves-na-mao',
            'load_schedule' => 'Atualização contínua, sob demanda, com cache máximo de 5 minutos.',
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $this->tenantFor($request);
        if ($tenant instanceof \Illuminate\Http\JsonResponse) {
            return $tenant;
        }

        $validator = Validator::make($request->all(), [
            'client_email' => 'required|email|max:255',
            'token' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $metadata = $tenant->metadata ?? [];
        $metadata['chaves_na_mao_email'] = trim((string) $request->input('client_email'));
        if ($request->filled('token')) {
            $metadata['chaves_na_mao_token_encrypted'] = Crypt::encryptString((string) $request->input('token'));
            unset($metadata['chaves_na_mao_token']);
        }
        $tenant->update(['metadata' => $metadata]);

        return $this->show($request);
    }

    private function tenantFor(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $tenantId = $user->role === 'super_admin'
            ? ($request->input('tenant_id') ?: $user->tenant_id)
            : $user->tenant_id;

        return Tenant::find($tenantId)
            ?: response()->json(['error' => 'Tenant not found'], 404);
    }
}
