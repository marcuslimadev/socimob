<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Carbon;

class LeadService
{
    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits ?: null;
    }

    public function findExisting(?int $tenantId = null, ?string $email = null, ?string ...$phones): ?Lead
    {
        $normalizedPhones = array_values(array_unique(array_filter(array_map(
            fn (?string $phone) => $this->normalizePhone($phone),
            $phones
        ))));

        if (!$email && empty($normalizedPhones)) {
            return null;
        }

        $query = Lead::query();

        $query->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        if (!$tenantId) {
            $query->whereNull('tenant_id');
        }

        $query->where(function ($q) use ($email, $normalizedPhones) {
            $hasCondition = false;

            if ($email) {
                $q->where('email', $email);
                $hasCondition = true;
            }

            foreach ($normalizedPhones as $phone) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $q->{$method}(function ($phoneQuery) use ($phone) {
                    $phoneQuery->where('telefone', $phone)
                        ->orWhere('whatsapp', $phone);
                });
                $hasCondition = true;
            }
        });

        return $query->first();
    }

    public function saveUnique(array $data): Lead
    {
        $tenantId = $data['tenant_id'] ?? null;
        $email = $data['email'] ?? null;
        $telefone = $this->normalizePhone($data['telefone'] ?? null);
        $whatsapp = $this->normalizePhone($data['whatsapp'] ?? null);

        $existingLead = $this->findExisting($tenantId, $email, $telefone, $whatsapp);

        if ($existingLead) {
            $payload = array_filter([
                'nome' => $data['nome'] ?? null,
                'email' => $email,
                'telefone' => $telefone ?? ($data['telefone'] ?? null),
                'whatsapp' => $whatsapp ?? ($data['whatsapp'] ?? null),
                'status' => $data['status'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'localizacao' => $data['localizacao'] ?? null,
                'quartos' => $data['quartos'] ?? null,
                'suites' => $data['suites'] ?? null,
                'garagem' => $data['garagem'] ?? null,
                'renda_mensal' => $data['renda_mensal'] ?? null,
                'estado_civil' => $data['estado_civil'] ?? null,
                'composicao_familiar' => $data['composicao_familiar'] ?? null,
                'profissao' => $data['profissao'] ?? null,
                'fonte_renda' => $data['fonte_renda'] ?? null,
                'financiamento_status' => $data['financiamento_status'] ?? null,
                'prazo_compra' => $data['prazo_compra'] ?? null,
                'objetivo_compra' => $data['objetivo_compra'] ?? null,
                'preferencia_tipo_imovel' => $data['preferencia_tipo_imovel'] ?? null,
                'preferencia_bairro' => $data['preferencia_bairro'] ?? null,
                'preferencia_lazer' => $data['preferencia_lazer'] ?? null,
                'preferencia_seguranca' => $data['preferencia_seguranca'] ?? null,
                'observacoes_cliente' => $data['observacoes_cliente'] ?? null,
                'caracteristicas_desejadas' => $data['caracteristicas_desejadas'] ?? null,
                'state' => $data['state'] ?? null,
                'chaves_na_mao_status' => $data['chaves_na_mao_status'] ?? null,
                'chaves_na_mao_sent_at' => $data['chaves_na_mao_sent_at'] ?? null,
                'chaves_na_mao_response' => $data['chaves_na_mao_response'] ?? null,
                'chaves_na_mao_error' => $data['chaves_na_mao_error'] ?? null,
                'chaves_na_mao_retries' => $data['chaves_na_mao_retries'] ?? null,
            ], fn ($value) => $value !== null);

            $existingLead->fill($payload);

            if (isset($data['primeira_interacao']) && empty($existingLead->primeira_interacao)) {
                $existingLead->primeira_interacao = $data['primeira_interacao'];
            }

            if (isset($data['ultima_interacao'])) {
                $existingLead->ultima_interacao = $data['ultima_interacao'];
            }

            $existingLead->save();

            return $existingLead;
        }

        $data['telefone'] = $telefone ?? ($data['telefone'] ?? ($whatsapp ?? ''));
        $data['whatsapp'] = $whatsapp ?? ($data['whatsapp'] ?? null);

        if (empty($data['telefone'])) {
            $data['telefone'] = '00000000000';
        }

        if (!isset($data['primeira_interacao'])) {
            $data['primeira_interacao'] = Carbon::now();
        }

        if (!isset($data['ultima_interacao'])) {
            $data['ultima_interacao'] = Carbon::now();
        }

        return Lead::create($data);
    }
}
