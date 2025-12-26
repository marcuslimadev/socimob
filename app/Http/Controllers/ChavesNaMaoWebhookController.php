<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChavesNaMaoWebhookController extends Controller
{
    /**
     * Recebe leads do Chaves na Mão via webhook
     */
    public function receive(Request $request)
    {
        // Validar autenticação
        $authResult = $this->validateAuthentication($request);
        if ($authResult !== true) {
            return $authResult;
        }

        try {
            // Capturar dados do lead (JSON)
            $leadData = $request->json()->all();
            
            // Fallback para dados do body se json() retornar vazio
            if (empty($leadData)) {
                $leadData = json_decode($request->getContent(), true) ?? [];
            }

            Log::info('📥 Lead recebido do Chaves na Mão', [
                'lead_id' => $leadData['id'] ?? 'N/A',
                'segment' => $leadData['segment'] ?? 'N/A',
                'name' => $leadData['name'] ?? 'N/A',
                'payload_keys' => array_keys($leadData)
            ]);

            // Processar e salvar lead
            $lead = $this->processLead($leadData);

            Log::info('✅ Lead processado com sucesso', [
                'internal_id' => $lead->id,
                'external_id' => $leadData['id'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lead recebido e processado',
                'lead_id' => $lead->id
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Erro ao processar lead do Chaves na Mão', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar lead'
            ], 500);
        }
    }

    /**
     * Valida autenticação Basic Auth
     */
    private function validateAuthentication(Request $request)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            Log::warning('⚠️ Webhook sem autenticação', [
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Autenticação necessária'], 401);
        }

        // Decodificar credenciais
        $authToken = str_replace('Basic ', '', $authHeader);
        $credentials = base64_decode($authToken);
        
        if (!str_contains($credentials, ':')) {
            return response()->json(['error' => 'Formato de autenticação inválido'], 401);
        }

        [$email, $token] = explode(':', $credentials, 2);

        // Validar credenciais
        $expectedEmail = env('EXCLUSIVA_MAIL_CHAVES_NA_MAO');
        $expectedToken = env('EXCLUSIVA_CHAVES_NA_MAO');

        if ($email !== $expectedEmail || $token !== $expectedToken) {
            Log::warning('🔒 Tentativa de acesso não autorizada', [
                'email_received' => $email,
                'ip' => $request->ip()
            ]);
            return response()->json(['error' => 'Credenciais inválidas'], 401);
        }

        return true;
    }

    /**
     * Processa dados do lead e salva no banco
     */
    private function processLead(array $data): Lead
    {
        $segment = $data['segment'] ?? 'REAL_ESTATE';
        $isVehicle = $segment === 'VEHICLE';

        // Preparar dados do lead
        $leadData = [
            'tenant_id' => 1, // Exclusiva
            'nome' => $data['name'] ?? 'Lead Chaves na Mão',
            'email' => $data['email'] ?? '',
            'telefone' => $data['phone'] ?? '00000000000', // Telefone obrigatório no banco
            'status' => 'novo',
            'observacoes' => $this->buildObservacoes($data, $isVehicle),
        ];

        // Dados específicos de imóveis
        if (!$isVehicle && isset($data['ad'])) {
            $ad = $data['ad'];
            
            if (isset($ad['rooms'])) {
                $leadData['quartos'] = $ad['rooms'];
            }
            if (isset($ad['suites'])) {
                $leadData['suites'] = $ad['suites'];
            }
            if (isset($ad['garages'])) {
                $leadData['garagem'] = $ad['garages'];
            }
            if (isset($ad['neighborhood']) || isset($ad['city'])) {
                $leadData['localizacao'] = trim(
                    ($ad['neighborhood'] ?? '') . ', ' . ($ad['city'] ?? '')
                );
            }
            if (isset($ad['price'])) {
                $leadData['budget_max'] = (float) $ad['price'];
            }
        }

        // Criar ou atualizar lead
        $lead = Lead::create($leadData);

        return $lead;
    }

    /**
     * Constrói observações com informações do anúncio
     */
    private function buildObservacoes(array $data, bool $isVehicle): string
    {
        $obs = [];

        // Mensagem do lead
        if (!empty($data['message'])) {
            $obs[] = "💬 Mensagem: " . $data['message'];
        }

        // Origem
        $obs[] = "🔗 Origem: Chaves na Mão (ID: " . ($data['id'] ?? 'N/A') . ")";

        // Informações do anúncio
        if (isset($data['ad'])) {
            $ad = $data['ad'];
            
            if ($isVehicle) {
                // Veículo
                $obs[] = "🚗 Veículo: " . 
                    ($ad['brand'] ?? '') . ' ' . 
                    ($ad['model'] ?? '') . ' ' . 
                    ($ad['year'] ?? '');
            } else {
                // Imóvel
                $obs[] = "🏠 Imóvel: " . 
                    ($ad['type'] ?? 'Não especificado') . 
                    ' - ' . 
                    ($ad['purpose'] ?? '');
                
                if (isset($ad['reference'])) {
                    $obs[] = "📋 Referência: " . $ad['reference'];
                }
            }

            if (isset($ad['title'])) {
                $obs[] = "📝 Anúncio: " . $ad['title'];
            }
        }

        return implode("\n", $obs);
    }
}
