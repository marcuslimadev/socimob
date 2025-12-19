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
        // Aqui você pode adicionar lógica mais sofisticada
        // Por enquanto, retorna false para exigir keywords explícitas
        return false;
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
}
