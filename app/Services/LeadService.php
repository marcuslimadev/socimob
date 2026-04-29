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

        $raw = trim(str_replace('whatsapp:', '', (string) $phone));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/\D+/', '', $raw);
            return $digits ? '+' . $digits : null;
        }

        if (str_starts_with($raw, '00')) {
            $digits = preg_replace('/\D+/', '', substr($raw, 2));
            return $digits ? '+' . $digits : null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return null;
        }

        // Se não veio com DDI e parece número BR (10 ou 11 dígitos), prefixar 55
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }

        return '+' . $digits;
    }

    private function buildPhoneVariants(?string $phone): array
    {
        $normalized = $this->normalizePhone($phone);
        if (!$normalized) {
            return [];
        }

        $digits = ltrim($normalized, '+');

        return array_values(array_unique([
            $normalized,
            $digits,
            '+' . $digits,
            'whatsapp:' . $normalized,
            'whatsapp:+' . $digits,
            'whatsapp:' . $digits,
        ]));
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
                $variants = $this->buildPhoneVariants($phone);
                $targets = !empty($variants) ? $variants : [$phone];
                $method = $hasCondition ? 'orWhere' : 'where';
                $q->{$method}(function ($phoneQuery) use ($targets) {
                    $phoneQuery->whereIn('telefone', $targets)
                        ->orWhereIn('whatsapp', $targets);
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

            if (($payload['status'] ?? null) === 'novo' && !empty($existingLead->status) && $existingLead->status !== 'novo') {
                unset($payload['status']);
            }

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

    /**
     * Busca avançada de leads com múltiplos critérios
     *
     * @param int|null $tenantId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function searchAdvanced(?int $tenantId, array $filters = [])
    {
        $query = Lead::query();

        // Filtro por tenant
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Filtro por status
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Filtro por nome (busca parcial)
        if (!empty($filters['nome'])) {
            $query->where('nome', 'like', '%' . $filters['nome'] . '%');
        }

        // Filtro por email (busca exata)
        if (!empty($filters['email'])) {
            $query->where('email', $filters['email']);
        }

        // Filtro por telefone
        if (!empty($filters['telefone'])) {
            $normalized = $this->normalizePhone($filters['telefone']);
            $query->where(function($q) use ($normalized) {
                $q->where('telefone', $normalized)
                  ->orWhere('whatsapp', $normalized);
            });
        }

        // Filtro por corretor
        if (!empty($filters['corretor_id'])) {
            $query->where('corretor_id', $filters['corretor_id']);
        }

        // Filtro por orçamento
        if (!empty($filters['budget_min'])) {
            $query->where('budget_max', '>=', $filters['budget_min']);
        }

        if (!empty($filters['budget_max'])) {
            $query->where('budget_min', '<=', $filters['budget_max']);
        }

        // Filtro por localização
        if (!empty($filters['localizacao'])) {
            $query->where('localizacao', 'like', '%' . $filters['localizacao'] . '%');
        }

        // Filtro por tipo de imóvel preferido
        if (!empty($filters['preferencia_tipo_imovel'])) {
            $query->where('preferencia_tipo_imovel', 'like', '%' . $filters['preferencia_tipo_imovel'] . '%');
        }

        // Filtro por bairro preferido
        if (!empty($filters['preferencia_bairro'])) {
            $query->where('preferencia_bairro', 'like', '%' . $filters['preferencia_bairro'] . '%');
        }

        // Filtro por quartos
        if (!empty($filters['quartos'])) {
            $query->where('quartos', '>=', $filters['quartos']);
        }

        // Filtro por data de criação
        if (!empty($filters['data_inicio'])) {
            $query->where('created_at', '>=', $filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $query->where('created_at', '<=', $filters['data_fim']);
        }

        // Filtro por última interação
        if (!empty($filters['ultima_interacao_inicio'])) {
            $query->where('ultima_interacao', '>=', $filters['ultima_interacao_inicio']);
        }

        if (!empty($filters['ultima_interacao_fim'])) {
            $query->where('ultima_interacao', '<=', $filters['ultima_interacao_fim']);
        }

        // Filtro por prazo de compra
        if (!empty($filters['prazo_compra'])) {
            $query->where('prazo_compra', $filters['prazo_compra']);
        }

        // Ordenação
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDirection = $filters['order_direction'] ?? 'desc';
        $query->orderBy($orderBy, $orderDirection);

        return $query;
    }

    /**
     * Encontrar leads duplicados
     *
     * @param int|null $tenantId
     * @return array
     */
    public function findDuplicates(?int $tenantId = null): array
    {
        $query = Lead::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $leads = $query->get();
        $duplicates = [];
        $processed = [];

        foreach ($leads as $lead) {
            if (in_array($lead->id, $processed)) {
                continue;
            }

            $similar = [];

            // Buscar por email idêntico
            if ($lead->email) {
                $emailMatches = $leads->where('email', $lead->email)
                    ->where('id', '!=', $lead->id)
                    ->pluck('id')
                    ->toArray();
                $similar = array_merge($similar, $emailMatches);
            }

            // Buscar por telefone normalizado
            if ($lead->telefone) {
                $normalized = $this->normalizePhone($lead->telefone);
                $phoneMatches = $leads->filter(function($l) use ($normalized, $lead) {
                    if ($l->id === $lead->id) {
                        return false;
                    }
                    return $this->normalizePhone($l->telefone) === $normalized
                        || $this->normalizePhone($l->whatsapp) === $normalized;
                })->pluck('id')->toArray();
                $similar = array_merge($similar, $phoneMatches);
            }

            // Buscar por WhatsApp normalizado
            if ($lead->whatsapp) {
                $normalized = $this->normalizePhone($lead->whatsapp);
                $whatsappMatches = $leads->filter(function($l) use ($normalized, $lead) {
                    if ($l->id === $lead->id) {
                        return false;
                    }
                    return $this->normalizePhone($l->telefone) === $normalized
                        || $this->normalizePhone($l->whatsapp) === $normalized;
                })->pluck('id')->toArray();
                $similar = array_merge($similar, $whatsappMatches);
            }

            $similar = array_unique($similar);

            if (!empty($similar)) {
                $duplicates[] = [
                    'master' => $lead->id,
                    'duplicates' => $similar,
                    'reason' => 'email_or_phone_match',
                ];

                $processed[] = $lead->id;
                $processed = array_merge($processed, $similar);
            }
        }

        return $duplicates;
    }

    /**
     * Mesclar leads duplicados
     *
     * @param int $masterId
     * @param array $duplicateIds
     * @return Lead
     */
    public function mergeDuplicates(int $masterId, array $duplicateIds): Lead
    {
        $master = Lead::findOrFail($masterId);
        $duplicates = Lead::whereIn('id', $duplicateIds)->get();

        foreach ($duplicates as $duplicate) {
            // Mesclar dados não vazios
            if (empty($master->email) && !empty($duplicate->email)) {
                $master->email = $duplicate->email;
            }

            if (empty($master->telefone) && !empty($duplicate->telefone)) {
                $master->telefone = $duplicate->telefone;
            }

            if (empty($master->whatsapp) && !empty($duplicate->whatsapp)) {
                $master->whatsapp = $duplicate->whatsapp;
            }

            // Mesclar observações
            if (!empty($duplicate->observacoes)) {
                $master->observacoes = ($master->observacoes ?? '') . "\n\n[Mesclado de Lead #{$duplicate->id}]\n" . $duplicate->observacoes;
            }

            // Usar data de primeira interação mais antiga
            if ($duplicate->primeira_interacao &&
                (!$master->primeira_interacao || $duplicate->primeira_interacao < $master->primeira_interacao)) {
                $master->primeira_interacao = $duplicate->primeira_interacao;
            }

            // Usar data de última interação mais recente
            if ($duplicate->ultima_interacao &&
                (!$master->ultima_interacao || $duplicate->ultima_interacao > $master->ultima_interacao)) {
                $master->ultima_interacao = $duplicate->ultima_interacao;
            }

            // Transferir relacionamentos
            $duplicate->conversas()->update(['lead_id' => $master->id]);
            $duplicate->documents()->update(['lead_id' => $master->id]);
            $duplicate->propertyMatches()->update(['lead_id' => $master->id]);

            // Deletar duplicata
            $duplicate->delete();
        }

        $master->save();

        return $master;
    }

    /**
     * Validar dados do lead
     *
     * @param array $data
     * @return array Array com 'valid' (bool) e 'errors' (array)
     */
    public function validateLeadData(array $data): array
    {
        $errors = [];

        // Validar nome
        if (empty($data['nome']) || strlen($data['nome']) < 3) {
            $errors['nome'] = 'Nome deve ter no mínimo 3 caracteres';
        }

        // Validar email (se fornecido)
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido';
        }

        // Validar telefone (obrigatório)
        if (empty($data['telefone']) && empty($data['whatsapp'])) {
            $errors['telefone'] = 'Telefone ou WhatsApp é obrigatório';
        }

        // Validar telefone (formato)
        if (!empty($data['telefone'])) {
            $normalized = $this->normalizePhone($data['telefone']);
            if (!$normalized || strlen($normalized) < 10 || strlen($normalized) > 11) {
                $errors['telefone'] = 'Telefone deve ter 10 ou 11 dígitos';
            }
        }

        // Validar WhatsApp (formato)
        if (!empty($data['whatsapp'])) {
            $normalized = $this->normalizePhone($data['whatsapp']);
            if (!$normalized || strlen($normalized) < 10 || strlen($normalized) > 11) {
                $errors['whatsapp'] = 'WhatsApp deve ter 10 ou 11 dígitos';
            }
        }

        // Validar orçamento
        if (!empty($data['budget_min']) && !empty($data['budget_max'])) {
            if ($data['budget_min'] > $data['budget_max']) {
                $errors['budget'] = 'Orçamento mínimo não pode ser maior que o máximo';
            }
        }

        // Validar CPF (se fornecido)
        if (!empty($data['cpf']) && !$this->validateCPF($data['cpf'])) {
            $errors['cpf'] = 'CPF inválido';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validar CPF
     *
     * @param string $cpf
     * @return bool
     */
    private function validateCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) != 11) {
            return false;
        }

        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validar dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcular score de qualificação do lead (0-100)
     *
     * @param Lead $lead
     * @return int
     */
    public function calculateLeadScore(Lead $lead): int
    {
        $score = 0;

        // +20 pontos: Tem email
        if (!empty($lead->email)) {
            $score += 20;
        }

        // +15 pontos: Tem telefone/WhatsApp válido
        if (!empty($lead->telefone) || !empty($lead->whatsapp)) {
            $score += 15;
        }

        // +25 pontos: Orçamento definido
        if (!empty($lead->budget_min) && !empty($lead->budget_max)) {
            $score += 25;
        } elseif (!empty($lead->budget_min) || !empty($lead->budget_max)) {
            $score += 12;
        }

        // +15 pontos: Preferências definidas
        if (!empty($lead->preferencia_tipo_imovel) || !empty($lead->preferencia_bairro)) {
            $score += 15;
        }

        // +10 pontos: Localização definida
        if (!empty($lead->localizacao)) {
            $score += 10;
        }

        // +10 pontos: Prazo de compra definido
        if (!empty($lead->prazo_compra)) {
            $score += 10;
        }

        // +5 pontos: Corretor atribuído
        if (!empty($lead->corretor_id)) {
            $score += 5;
        }

        // Reduzir pontos para leads antigos sem interação
        if ($lead->ultima_interacao) {
            $diasSemInteracao = Carbon::now()->diffInDays($lead->ultima_interacao);

            if ($diasSemInteracao > 90) {
                $score -= 20;
            } elseif ($diasSemInteracao > 60) {
                $score -= 15;
            } elseif ($diasSemInteracao > 30) {
                $score -= 10;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Atualizar score de um lead
     *
     * @param int $leadId
     * @return int
     */
    public function updateLeadScore(int $leadId): int
    {
        $lead = Lead::findOrFail($leadId);
        $score = $this->calculateLeadScore($lead);

        // Armazenar score em observações ou campo customizado
        // (pode criar um campo 'score' na tabela leads se preferir)
        $lead->observacoes = preg_replace(
            '/\[Score: \d+\]/',
            '',
            $lead->observacoes ?? ''
        );

        $lead->observacoes = trim($lead->observacoes) . "\n[Score: {$score}]";
        $lead->save();

        return $score;
    }

    /**
     * Normalizar dados do lead antes de salvar
     *
     * @param array $data
     * @return array
     */
    public function normalizeLeadData(array $data): array
    {
        // Normalizar telefones
        if (isset($data['telefone'])) {
            $data['telefone'] = $this->normalizePhone($data['telefone']);
        }

        if (isset($data['whatsapp'])) {
            $data['whatsapp'] = $this->normalizePhone($data['whatsapp']);
        }

        // Normalizar email
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Normalizar CPF
        if (isset($data['cpf'])) {
            $data['cpf'] = preg_replace('/\D/', '', $data['cpf']);
        }

        // Capitalizar nome
        if (isset($data['nome'])) {
            $data['nome'] = ucwords(strtolower(trim($data['nome'])));
        }

        return $data;
    }
}
