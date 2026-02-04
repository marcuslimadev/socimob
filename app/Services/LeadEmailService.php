<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Serviço de Email Automático para Leads
 * 
 * Envia emails automáticos quando um lead válido é criado,
 * usando IA para gerar conteúdo contextualizado
 */
class LeadEmailService
{
    private OpenAIService $openAIService;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
    }

    /**
     * Enviar email de boas-vindas para lead
     *
     * @param Lead $lead
     * @return array
     */
    public function enviarEmailBoasVindas(Lead $lead): array
    {
        try {
            // 1. Validar email
            if (!$this->validarEmail($lead->email)) {
                Log::warning('[LeadEmailService] Email inválido', [
                    'lead_id' => $lead->id,
                    'email' => $lead->email
                ]);
                return [
                    'success' => false,
                    'error' => 'Email inválido'
                ];
            }

            // 2. Buscar tenant e configurações
            $tenant = Tenant::find($lead->tenant_id);
            if (!$tenant) {
                Log::error('[LeadEmailService] Tenant não encontrado', [
                    'lead_id' => $lead->id,
                    'tenant_id' => $lead->tenant_id
                ]);
                return [
                    'success' => false,
                    'error' => 'Tenant não encontrado'
                ];
            }

            // 3. Gerar conteúdo do email com IA
            $emailContent = $this->gerarConteudoEmail($lead, $tenant);

            // 4. Enviar email
            $this->enviarEmail($lead, $tenant, $emailContent);

            Log::info('[LeadEmailService] Email enviado com sucesso', [
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'tenant_id' => $tenant->id
            ]);

            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'email_enviado',
                'Email de boas-vindas enviado para lead',
                [
                    'lead_id' => $lead->id,
                    'email' => $lead->email,
                    'tenant_id' => $tenant->id
                ]
            );

            return [
                'success' => true,
                'message' => 'Email enviado com sucesso'
            ];

        } catch (\Exception $e) {
            Log::error('[LeadEmailService] Erro ao enviar email', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_AUTOMATION,
                'email_erro',
                'Erro ao enviar email para lead',
                [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage()
                ]
            );

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validar email
     *
     * @param string|null $email
     * @return bool
     */
    private function validarEmail(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Gerar conteúdo do email usando IA
     *
     * @param Lead $lead
     * @param Tenant $tenant
     * @return array
     */
    private function gerarConteudoEmail(Lead $lead, Tenant $tenant): array
    {
        // Preparar contexto do lead
        $contexto = $this->prepararContextoLead($lead);

        // Prompt para IA gerar email
        $prompt = $this->criarPromptEmail($lead, $tenant, $contexto);

        try {
            // Gerar conteúdo com IA
            $systemPrompt = "Você é um assistente de atendimento imobiliário da {$tenant->name}. Crie emails profissionais, acolhedores e persuasivos para novos leads.";

            if (method_exists($this->openAIService, 'chat')) {
                $response = $this->openAIService->chat([
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ], $lead->tenant_id);

                $conteudoIA = $response['choices'][0]['message']['content'] ?? '';
            } else {
                $conteudoIA = $this->openAIService->generateSimpleMessage($systemPrompt, $prompt);
            }

            if (empty($conteudoIA)) {
                return $this->gerarEmailPadrao($lead, $tenant);
            }

            // Parse do conteúdo (esperamos formato: ASSUNTO: ... | CORPO: ...)
            return $this->parseConteudoEmail($conteudoIA, $lead, $tenant);

        } catch (\Exception $e) {
            Log::warning('[LeadEmailService] Erro ao gerar conteúdo com IA, usando template padrão', [
                'error' => $e->getMessage()
            ]);

            // Fallback: usar template padrão
            return $this->gerarEmailPadrao($lead, $tenant);
        }
    }

    /**
     * Preparar contexto do lead para IA
     *
     * @param Lead $lead
     * @return string
     */
    private function prepararContextoLead(Lead $lead): string
    {
        $partes = [];

        if ($lead->nome) {
            $partes[] = "Nome: {$lead->nome}";
        }

        if ($lead->tipo_interesse) {
            $partes[] = "Interesse: {$lead->tipo_interesse}";
        }

        if ($lead->valor_minimo || $lead->valor_maximo) {
            $valores = [];
            if ($lead->valor_minimo) $valores[] = "mín R$ " . number_format($lead->valor_minimo, 2, ',', '.');
            if ($lead->valor_maximo) $valores[] = "máx R$ " . number_format($lead->valor_maximo, 2, ',', '.');
            $partes[] = "Faixa de preço: " . implode(' - ', $valores);
        }

        if ($lead->cidade) {
            $partes[] = "Cidade: {$lead->cidade}";
        }

        if ($lead->bairro) {
            $partes[] = "Bairro: {$lead->bairro}";
        }

        if ($lead->quartos) {
            $partes[] = "Quartos: {$lead->quartos}";
        }

        if ($lead->observacoes) {
            $partes[] = "Observações: {$lead->observacoes}";
        }

        return implode("\n", $partes);
    }

    /**
     * Criar prompt para IA gerar email
     *
     * @param Lead $lead
     * @param Tenant $tenant
     * @param string $contexto
     * @return string
     */
    private function criarPromptEmail(Lead $lead, Tenant $tenant, string $contexto): string
    {
        $whatsapp = $this->formatarWhatsApp($tenant->contact_phone);
        $linkWhatsApp = "https://wa.me/{$whatsapp}";

        return <<<PROMPT
Crie um email de boas-vindas para um novo lead que demonstrou interesse em imóveis.

DADOS DO LEAD:
{$contexto}

ORIGEM: Lead recebido via integração Chaves na Mão

INSTRUÇÕES:
1. Use tom profissional mas acolhedor
2. Mencione que recebemos o interesse dele/dela
3. Faça referência aos dados específicos que ele forneceu (tipo de imóvel, localização, etc)
4. Destaque que temos opções que podem atender suas necessidades
5. Inclua um call-to-action claro para contato via WhatsApp
6. Mantenha o email curto e direto (máximo 3 parágrafos)

FORMATO DA RESPOSTA:
ASSUNTO: [assunto do email em 1 linha]
---
CORPO:
[corpo do email em HTML simples, sem tags <html>, <body>, apenas conteúdo]

IMPORTANTE:
- Inclua um botão/link de WhatsApp apontando para: {$linkWhatsApp}
- Use o telefone de contato: {$tenant->contact_phone}
- Assine como equipe {$tenant->name}
PROMPT;
    }

    /**
     * Parse do conteúdo gerado pela IA
     *
     * @param string $conteudo
     * @param Lead $lead
     * @param Tenant $tenant
     * @return array
     */
    private function parseConteudoEmail(string $conteudo, Lead $lead, Tenant $tenant): array
    {
        // Tentar extrair assunto e corpo
        if (preg_match('/ASSUNTO:\s*(.+?)\s*---\s*CORPO:\s*(.+)/s', $conteudo, $matches)) {
            return [
                'assunto' => trim($matches[1]),
                'corpo' => trim($matches[2])
            ];
        }

        // Fallback: usar primeira linha como assunto e resto como corpo
        $linhas = explode("\n", $conteudo);
        $assunto = trim($linhas[0]);
        $corpo = trim(implode("\n", array_slice($linhas, 1)));

        if (empty($assunto)) {
            $assunto = "Bem-vindo(a) à {$tenant->name}!";
        }

        return [
            'assunto' => $assunto,
            'corpo' => $corpo ?: $this->gerarEmailPadrao($lead, $tenant)['corpo']
        ];
    }

    /**
     * Gerar email padrão (fallback)
     *
     * @param Lead $lead
     * @param Tenant $tenant
     * @return array
     */
    private function gerarEmailPadrao(Lead $lead, Tenant $tenant): array
    {
        $nomeCliente = $lead->nome ? explode(' ', $lead->nome)[0] : 'Cliente';
        $whatsapp = $this->formatarWhatsApp($tenant->contact_phone);
        $linkWhatsApp = "https://wa.me/{$whatsapp}?text=" . urlencode("Olá! Recebi o email de vocês e gostaria de conversar sobre imóveis.");

        $corpo = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: {$tenant->primary_color};">Olá, {$nomeCliente}!</h2>
    
    <p>Recebemos seu interesse em imóveis através do <strong>Chaves na Mão</strong> e queremos agradecer por escolher a <strong>{$tenant->name}</strong>!</p>
    
    <p>Nossa equipe está analisando suas preferências e já estamos selecionando as melhores opções de imóveis para você.</p>
    
    <p>Para agilizar o atendimento e tirar todas as suas dúvidas, entre em contato conosco pelo WhatsApp:</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <a href="{$linkWhatsApp}" 
           style="background-color: #25D366; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
            📱 Falar no WhatsApp
        </a>
    </div>
    
    <p style="color: #666; font-size: 14px;">
        Estamos à disposição!<br>
        <strong>{$tenant->name}</strong><br>
        📞 {$tenant->contact_phone}
    </p>
</div>
HTML;

        return [
            'assunto' => "Bem-vindo(a) à {$tenant->name}! 🏠",
            'corpo' => $corpo
        ];
    }

    /**
     * Formatar telefone para WhatsApp (formato internacional sem +)
     *
     * @param string|null $telefone
     * @return string
     */
    private function formatarWhatsApp(?string $telefone): string
    {
        if (empty($telefone)) {
            return '';
        }

        // Remover caracteres não numéricos
        $numeros = preg_replace('/[^0-9]/', '', $telefone);

        // Se já começa com 55 (Brasil), retornar
        if (Str::startsWith($numeros, '55')) {
            return $numeros;
        }

        // Adicionar código do Brasil
        return '55' . $numeros;
    }

    /**
     * Enviar email
     *
     * @param Lead $lead
     * @param Tenant $tenant
     * @param array $emailContent
     * @return void
     */
    private function enviarEmail(Lead $lead, Tenant $tenant, array $emailContent): void
    {
        $assunto = $emailContent['assunto'];
        $corpo = $emailContent['corpo'];

        Mail::send([], [], function ($message) use ($lead, $tenant, $assunto, $corpo) {
            $message->from($tenant->contact_email ?? 'noreply@socimob.com', $tenant->name)
                    ->to($lead->email, $lead->nome)
                    ->subject($assunto)
                    ->html($corpo);
        });
    }
}
