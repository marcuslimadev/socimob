<?php

namespace App\Services;

/**
 * Serviço para detecção automática de stage/estágio da conversa
 */
class StageDetectionService
{
    /**
     * Detectar próximo stage baseado no contexto da conversa
     * 
     * @param string $currentStage Stage atual
     * @param string $message Mensagem do usuário
     * @param array $context Contexto da conversa (mensagens anteriores)
     * @return string Próximo stage
     */
    public function detectNextStage($currentStage, $message, $context = [])
    {
        $messageLower = mb_strtolower($message);
        
        // Palavras-chave por stage
        $keywords = [
            'orcamento' => ['reais', 'r$', 'mil', 'milhão', 'valor', 'orçamento', 'investir', 'pagar', 'custo'],
            'localizacao' => ['bairro', 'região', 'zona', 'perto', 'próximo', 'localização', 'onde', 'área', 'savassi', 'funcionários', 'lourdes'],
            'preferencias' => ['quarto', 'suite', 'suíte', 'vaga', 'garagem', 'elevador', 'piscina', 'academia', 'churrasqueira', 'varanda'],
            'interesse' => ['código', 'ref', 'referência', 'gostei', 'interessante', 'visitar', 'ver', 'conhecer'],
            'agendamento' => ['agendar', 'visita', 'quando', 'horário', 'disponível', 'posso', 'amanhã', 'hoje', 'semana']
        ];
        
        // Fluxo de stages
        $stageFlow = [
            'boas_vindas' => 'coleta_dados',
            'coleta_dados' => 'orcamento',
            'orcamento' => 'localizacao',
            'localizacao' => 'preferencias',
            'preferencias' => 'busca_imoveis',
            'busca_imoveis' => 'apresentacao',
            'apresentacao' => 'interesse',
            'interesse' => 'agendamento'
        ];
        
        // Se detectar orçamento, avança
        if ($this->containsKeywords($messageLower, $keywords['orcamento'])) {
            if ($currentStage === 'boas_vindas' || $currentStage === 'coleta_dados') {
                return 'orcamento';
            }
        }
        
        // Se detectar localização, avança
        if ($this->containsKeywords($messageLower, $keywords['localizacao'])) {
            if (in_array($currentStage, ['orcamento', 'coleta_dados'])) {
                return 'localizacao';
            }
        }
        
        // Se detectar preferências (quartos, suites, etc)
        if ($this->containsKeywords($messageLower, $keywords['preferencias'])) {
            if (in_array($currentStage, ['localizacao', 'orcamento'])) {
                return 'preferencias';
            }
        }
        
        // Se detectar interesse em imóvel específico
        if ($this->containsKeywords($messageLower, $keywords['interesse'])) {
            return 'interesse';
        }
        
        // Se detectar agendamento
        if ($this->containsKeywords($messageLower, $keywords['agendamento'])) {
            return 'agendamento';
        }
        
        // Progressão natural baseada no fluxo
        if (isset($stageFlow[$currentStage])) {
            // Se já tem informação suficiente neste stage, avançar
            if ($this->stageHasEnoughInfo($currentStage, $context)) {
                return $stageFlow[$currentStage];
            }
        }
        
        return $currentStage;
    }
    
    /**
     * Verificar se mensagem contém palavras-chave
     */
    private function containsKeywords($text, $keywords)
    {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar se stage tem informação suficiente para avançar
     */
    private function stageHasEnoughInfo($stage, $context)
    {
        $messages = $this->normalizeContextMessages($context);

        if (empty($messages)) {
            return false;
        }

        switch ($stage) {
            case 'boas_vindas':
                // Avança se já saudou
                return count($messages) >= 1;

            case 'coleta_dados':
                // Avança se coletou nome
                return $this->hasCollectedName($messages);

            case 'orcamento':
                // Avança se detectou orçamento em alguma mensagem
                return $this->hasCollectedBudget($messages);

            case 'localizacao':
                // Avança se detectou localização
                return $this->hasCollectedLocation($messages);

            case 'preferencias':
                // Avança se detectou preferências (quartos, suites, etc)
                return $this->hasCollectedPreferences($messages);

            case 'busca_imoveis':
                // Avança se fez busca (geralmente automático)
                return true;

            case 'apresentacao':
                // Avança se apresentou imóveis
                return $this->hasPresentedProperties($messages);

            default:
                return false;
        }
    }

    private function normalizeContextMessages($context): array
    {
        if (is_string($context)) {
            return $this->historyStringToMessages($context);
        }

        if (!is_array($context)) {
            return [];
        }

        if (isset($context['history']) && is_string($context['history'])) {
            return $this->historyStringToMessages($context['history']);
        }

        return array_values(array_filter(array_map(function ($message) {
            if (is_array($message)) {
                return ['content' => (string) ($message['content'] ?? '')];
            }

            if (is_string($message)) {
                return ['content' => $message];
            }

            return null;
        }, $context)));
    }

    private function historyStringToMessages(string $history): array
    {
        $messages = [];

        foreach (preg_split('/\R/u', $history) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $messages[] = [
                'content' => preg_replace('/^(Cliente|Atendente):\s*/iu', '', $line) ?? $line,
            ];
        }

        return $messages;
    }
    
    /**
     * Obter mensagem de transição para novo stage
     */
    public function getStageTransitionMessage($newStage)
    {
        $messages = [
            'coleta_dados' => 'Ótimo! Vamos começar. 📝',
            'orcamento' => 'Perfeito! Agora me conta sobre o orçamento. 💰',
            'localizacao' => 'Entendi! E sobre a localização? 📍',
            'preferencias' => 'Show! Agora me fala sobre suas preferências. 🏠',
            'busca_imoveis' => 'Deixa eu buscar os melhores imóveis para você! 🔍',
            'apresentacao' => 'Encontrei ótimas opções! Vou te mostrar. 🎯',
            'interesse' => 'Que legal que gostou! 😊',
            'agendamento' => 'Vamos agendar sua visita! 📅'
        ];
        
        return $messages[$newStage] ?? '';
    }

    /**
     * Verificar se nome foi coletado
     */
    private function hasCollectedName(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            // Detectar padrões de nome
            if (preg_match('/\b(me chamo|meu nome|sou o|sou a|eu sou)\b/i', $text)) {
                return true;
            }
            // Detectar se mensagem tem apenas um nome (2-4 palavras)
            $words = preg_split('/\s+/', trim($text));
            if (count($words) >= 2 && count($words) <= 4 && !preg_match('/\d/', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se orçamento foi coletado
     */
    private function hasCollectedBudget(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            // Detectar valores monetários
            if (preg_match('/\b(r\$|reais?|mil|milhão|milhões|\d{3,})\b/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se localização foi coletada
     */
    private function hasCollectedLocation(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            // Detectar menção a bairros, regiões, zonas
            if (preg_match('/\b(bairro|região|zona|centro|savassi|funcionários|lourdes|perto|próximo)\b/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se preferências foram coletadas
     */
    private function hasCollectedPreferences(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            // Detectar características desejadas
            if (preg_match('/\b(quarto|suite|suíte|vaga|garagem|elevador|piscina)\b/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se imóveis foram apresentados
     */
    private function hasPresentedProperties(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            $direction = $msg['direction'] ?? 'incoming';

            // Verificar se IA enviou código de imóvel
            if ($direction === 'outgoing' && preg_match('/\b(código|ref|referência|imóvel)\b.*\b[A-Z]{2,}\d+/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calcular score de qualificação do lead baseado na conversa
     *
     * @param array $context Histórico de mensagens
     * @param array $leadData Dados do lead
     * @return int Score de 0 a 100
     */
    public function calculateLeadScore(array $context, array $leadData = []): int
    {
        $score = 0;

        // +20: Tem nome identificado
        if (!empty($leadData['nome']) || $this->hasCollectedName($context)) {
            $score += 20;
        }

        // +25: Orçamento definido
        if (!empty($leadData['budget_min']) || !empty($leadData['budget_max']) || $this->hasCollectedBudget($context)) {
            $score += 25;
        }

        // +15: Localização definida
        if (!empty($leadData['localizacao']) || !empty($leadData['preferencia_bairro']) || $this->hasCollectedLocation($context)) {
            $score += 15;
        }

        // +15: Preferências definidas
        if (!empty($leadData['quartos']) || !empty($leadData['caracteristicas_desejadas']) || $this->hasCollectedPreferences($context)) {
            $score += 15;
        }

        // +10: Demonstrou interesse em imóvel específico
        if ($this->hasShownPropertyInterest($context)) {
            $score += 10;
        }

        // +10: Solicitou agendamento
        if ($this->hasRequestedScheduling($context)) {
            $score += 10;
        }

        // +5: Engajamento (respondeu mais de 3 mensagens)
        $incomingCount = 0;
        foreach ($context as $msg) {
            if (($msg['direction'] ?? '') === 'incoming') {
                $incomingCount++;
            }
        }
        if ($incomingCount >= 3) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Verificar se demonstrou interesse em imóvel específico
     */
    private function hasShownPropertyInterest(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            if (preg_match('/\b(gostei|interessante|quero ver|visitar|agendar)\b/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verificar se solicitou agendamento
     */
    private function hasRequestedScheduling(array $context): bool
    {
        foreach ($context as $msg) {
            $text = mb_strtolower($msg['content'] ?? '');
            if (preg_match('/\b(agendar|visita|quando|horário|disponível)\b/i', $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Analisar comportamento do lead e retornar insights
     *
     * @param array $context Histórico de mensagens
     * @param string $currentStage Stage atual
     * @return array Insights sobre o comportamento
     */
    public function analyzeLeadBehavior(array $context, string $currentStage): array
    {
        $incomingCount = 0;
        $outgoingCount = 0;
        $totalWords = 0;
        $avgResponseTimeMinutes = 0;
        $lastMessageTime = null;
        $responseTimes = [];

        foreach ($context as $msg) {
            $direction = $msg['direction'] ?? 'incoming';
            $content = $msg['content'] ?? '';
            $sentAt = $msg['sent_at'] ?? null;

            if ($direction === 'incoming') {
                $incomingCount++;
                $totalWords += str_word_count($content);

                // Calcular tempo de resposta
                if ($lastMessageTime && $sentAt) {
                    $diff = strtotime($sentAt) - strtotime($lastMessageTime);
                    if ($diff > 0 && $diff < 3600) { // Ignorar > 1 hora
                        $responseTimes[] = $diff / 60; // em minutos
                    }
                }
            } else {
                $outgoingCount++;
            }

            $lastMessageTime = $sentAt;
        }

        if (!empty($responseTimes)) {
            $avgResponseTimeMinutes = round(array_sum($responseTimes) / count($responseTimes), 1);
        }

        $avgWordsPerMessage = $incomingCount > 0 ? round($totalWords / $incomingCount, 1) : 0;

        // Determinar nível de engajamento
        $engagementLevel = 'baixo';
        if ($incomingCount >= 5 && $avgResponseTimeMinutes < 10) {
            $engagementLevel = 'alto';
        } elseif ($incomingCount >= 3) {
            $engagementLevel = 'médio';
        }

        // Determinar urgência
        $urgency = 'baixa';
        if ($avgResponseTimeMinutes < 5 && $avgWordsPerMessage > 10) {
            $urgency = 'alta';
        } elseif ($avgResponseTimeMinutes < 15) {
            $urgency = 'média';
        }

        // Calcular completude do funil
        $funnelCompleteness = $this->calculateFunnelCompleteness($context, $currentStage);

        return [
            'incoming_messages' => $incomingCount,
            'outgoing_messages' => $outgoingCount,
            'avg_words_per_message' => $avgWordsPerMessage,
            'avg_response_time_minutes' => $avgResponseTimeMinutes,
            'engagement_level' => $engagementLevel,
            'urgency' => $urgency,
            'funnel_completeness' => $funnelCompleteness,
            'current_stage' => $currentStage,
            'has_budget' => $this->hasCollectedBudget($context),
            'has_location' => $this->hasCollectedLocation($context),
            'has_preferences' => $this->hasCollectedPreferences($context),
            'has_property_interest' => $this->hasShownPropertyInterest($context),
        ];
    }

    /**
     * Calcular completude do funil (0-100%)
     */
    private function calculateFunnelCompleteness(array $context, string $currentStage): int
    {
        $stages = ['boas_vindas', 'coleta_dados', 'orcamento', 'localizacao', 'preferencias', 'busca_imoveis', 'apresentacao', 'interesse', 'agendamento'];
        $currentIndex = array_search($currentStage, $stages);

        if ($currentIndex === false) {
            return 0;
        }

        $completeness = round(($currentIndex / (count($stages) - 1)) * 100);

        return min(100, max(0, $completeness));
    }

    /**
     * Obter próxima ação recomendada para o corretor
     *
     * @param array $behaviorAnalysis Análise de comportamento
     * @param int $leadScore Score do lead
     * @return array Ação recomendada
     */
    public function getRecommendedAction(array $behaviorAnalysis, int $leadScore): array
    {
        $urgency = $behaviorAnalysis['urgency'] ?? 'baixa';
        $engagement = $behaviorAnalysis['engagement_level'] ?? 'baixo';
        $currentStage = $behaviorAnalysis['current_stage'] ?? 'boas_vindas';

        // Lead quente (score alto + engajamento alto)
        if ($leadScore >= 70 && $engagement === 'alto') {
            return [
                'action' => 'contact_immediately',
                'priority' => 'critical',
                'message' => 'Lead muito qualificado! Entre em contato imediatamente.',
                'suggested_channel' => 'phone_call',
            ];
        }

        // Lead com urgência alta
        if ($urgency === 'alta') {
            return [
                'action' => 'respond_quickly',
                'priority' => 'high',
                'message' => 'Lead demonstra urgência. Responda em até 30 minutos.',
                'suggested_channel' => 'whatsapp',
            ];
        }

        // Lead engajado mas sem informações completas
        if ($engagement === 'alto' && $leadScore < 50) {
            return [
                'action' => 'collect_more_info',
                'priority' => 'medium',
                'message' => 'Lead engajado mas faltam informações. Continue a conversa.',
                'suggested_channel' => 'whatsapp',
            ];
        }

        // Lead no stage de interesse
        if ($currentStage === 'interesse' || $currentStage === 'agendamento') {
            return [
                'action' => 'schedule_visit',
                'priority' => 'high',
                'message' => 'Lead pronto para agendamento. Ofereça horários de visita.',
                'suggested_channel' => 'whatsapp',
            ];
        }

        // Lead frio (baixo engajamento)
        if ($engagement === 'baixo') {
            return [
                'action' => 'nurture',
                'priority' => 'low',
                'message' => 'Lead precisa de nutrição. Envie conteúdo relevante.',
                'suggested_channel' => 'email',
            ];
        }

        // Padrão
        return [
            'action' => 'continue_conversation',
            'priority' => 'medium',
            'message' => 'Continue a conversa para qualificar o lead.',
            'suggested_channel' => 'whatsapp',
        ];
    }
}
