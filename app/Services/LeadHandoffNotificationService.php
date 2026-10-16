<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadHandoffLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifica Alexsandra e Roberto por WhatsApp sobre leads do Chaves na Mão
 * (mensagem do cliente + link wa.me com resposta automática) e atende ao
 * comando "Relatório" que qualquer um deles pode mandar para a Teresa
 * para receber um resumo de leads e reenviar os handoffs ainda pendentes.
 */
class LeadHandoffNotificationService
{
    private const RECIPIENTS = [
        [
            'name' => 'Alexsandra',
            'phone' => '5531975597278',
            'greeting' => 'Oi, sou a Alexsandra, recebi seu contato e estou disponível para iniciar seu atendimento.',
        ],
        [
            'name' => 'Roberto',
            'phone' => '5531997803274',
            'greeting' => 'Oi, sou o Roberto, recebi seu contato e estou disponível para iniciar seu atendimento.',
        ],
    ];

    /**
     * Template padrão (aprovado na Meta) para alertas operacionais: entrega mesmo com a janela de 24h
     * fechada. O texto é neutro (serve à Alexsandra e ao Roberto); o nome efetivo pode ser trocado em
     * tenant_configs.metadata.operational_whatsapp_template_name, sem deploy. Variáveis do corpo:
     * {{1}} tipo de alerta, {{2}} cliente, {{3}} resumo, {{4}} link (painel ou wa.me do cliente).
     */
    public const ALEXSANDRA_TEMPLATE_NAME = 'alerta_novo_lead_socimob';
    public const ALEXSANDRA_TEMPLATE_LANGUAGE = 'pt_BR';

    public function __construct(
        private ConversationAssignmentNotificationService $assignmentNotificationService,
        private LeadService $leadService
    ) {
    }

    /**
     * Leads que recebem o handoff dedicado (Chaves na Mão e mascote do portal) em vez do
     * alerta genérico de "novo lead".
     */
    public function isHandoffLead(Lead $lead): bool
    {
        $observacoes = (string) $lead->observacoes;

        return stripos($observacoes, 'Chaves na') !== false
            || stripos($observacoes, 'mascote do portal') !== false;
    }

    /**
     * Envia o handoff (mensagem do cliente + link wa.me) para Alexsandra e Roberto.
     * Marca no lead quem foi notificado e quando. Quem já constava em
     * atendimento_notificado_para não recebe de novo (permite reenviar só a quem falhou).
     * Retorna todos os nomes já notificados com sucesso (inclusive em tentativas anteriores).
     */
    public function notify(Lead $lead): array
    {
        $alreadyNotified = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $lead->atendimento_notificado_para)
        )));
        $notifiedTo = [];

        foreach (self::RECIPIENTS as $recipient) {
            if (in_array($recipient['name'], $alreadyNotified, true)) {
                continue;
            }

            $result = $this->sendHandoffMessage($lead, $recipient);

            if ($result['success'] ?? false) {
                $notifiedTo[] = $recipient['name'];
            }
        }

        $allNotified = array_values(array_unique(array_merge($alreadyNotified, $notifiedTo)));

        if (!empty($notifiedTo)) {
            // Não regride o status de leads que já avançaram no funil (ex.: qualificado, proposta)
            $status = in_array($lead->status, ['novo', null, ''], true) ? 'em_atendimento' : $lead->status;

            $lead->updateQuietly([
                'status' => $status,
                'atendimento_notificado_em' => now(),
                'atendimento_notificado_para' => implode(', ', $allNotified),
            ]);

            Log::info('[LeadHandoffNotificationService] Lead do Chaves na Mão notificado para atendimento humano', [
                'lead_id' => $lead->id,
                'notificado_para' => $allNotified,
            ]);
        }

        return $allNotified;
    }

    /**
     * Trata um comando recebido por WhatsApp de Alexsandra ou Roberto. Retorna um resultado
     * (para ser devolvido direto pelo processIncomingMessage, sem passar pela IA) quando o
     * remetente é um dos dois e a mensagem é o comando "Relatório"; caso contrário, null.
     */
    public function handleInboundCommand(string $telefone, string $body, int $tenantId): ?array
    {
        $recipient = $this->findRecipientByPhone($telefone);
        if (!$recipient) {
            return null;
        }

        if (!preg_match('/^relat[óo]rio$/iu', trim($body))) {
            return null;
        }

        Log::info('[LeadHandoffNotificationService] Comando de relatório recebido', [
            'recipient' => $recipient['name'],
            'tenant_id' => $tenantId,
        ]);

        $this->assignmentNotificationService->sendWhatsAppMessage(
            $tenantId,
            $recipient['phone'],
            $this->buildReportMessage($tenantId)
        );

        return [
            'success' => true,
            'message' => 'Relatório enviado para ' . $recipient['name'],
        ];
    }

    public function findRecipientByPhone(string $phone): ?array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return null;
        }

        foreach (self::RECIPIENTS as $recipient) {
            if ($digits === $recipient['phone'] || str_ends_with($digits, substr($recipient['phone'], -10))) {
                return $recipient;
            }
        }

        return null;
    }

    /**
     * Leads do Chaves na Mão ainda aguardando atendimento humano (status não avançou
     * além de "em_atendimento"), candidatos a reenvio quando a janela de 24h reabrir.
     */
    public function pendingLeads(int $tenantId, int $days = 14): Collection
    {
        return Lead::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'em_atendimento')
            ->where('observacoes', 'like', '%Chaves na%')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at')
            ->get();
    }

    public function buildReportMessage(int $tenantId): string
    {
        $hoje = now()->startOfDay();
        $semana = now()->subDays(7);

        $totalLeads = Lead::where('tenant_id', $tenantId)->count();
        $novosHoje = Lead::where('tenant_id', $tenantId)->where('created_at', '>=', $hoje)->count();
        $novosSemana = Lead::where('tenant_id', $tenantId)->where('created_at', '>=', $semana)->count();
        $emAtendimento = Lead::where('tenant_id', $tenantId)->where('status', 'em_atendimento')->count();
        $chavesNaMaoHoje = Lead::where('tenant_id', $tenantId)
            ->where('observacoes', 'like', '%Chaves na%')
            ->where('created_at', '>=', $hoje)
            ->count();

        return "📊 Relatório de leads\n"
            . "Total: {$totalLeads}\n"
            . "Novos hoje: {$novosHoje}\n"
            . "Novos nos últimos 7 dias: {$novosSemana}\n"
            . "Aguardando atendimento: {$emAtendimento}\n"
            . "Chaves na Mão hoje: {$chavesNaMaoHoje}";
    }

    public function sendHandoffMessage(Lead $lead, array $recipient): array
    {
        // Template primeiro (chega mesmo fora da janela de 24h); texto livre só como reserva.
        $templateResult = $this->sendTemplateAlert($lead, $recipient);
        if ($templateResult['success'] ?? false) {
            return $templateResult;
        }

        Log::warning('[LeadHandoffNotificationService] Falha ao enviar via template, tentando texto livre', [
            'lead_id' => $lead->id,
            'recipient' => $recipient['name'],
            'error' => $templateResult['error'] ?? 'Erro desconhecido',
        ]);

        $body = $this->buildMessage($lead, $recipient);

        try {
            $result = $this->assignmentNotificationService->sendWhatsAppMessage(
                (int) $lead->tenant_id,
                $recipient['phone'],
                $body
            );
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        if (!($result['success'] ?? false)) {
            Log::warning('[LeadHandoffNotificationService] Falha ao notificar responsável sobre lead do Chaves na Mão', [
                'lead_id' => $lead->id,
                'recipient' => $recipient['name'],
                'error' => $result['error'] ?? 'Erro desconhecido',
            ]);
        }

        return $result;
    }

    /**
     * Envia o alerta via template aprovado (funciona mesmo com a janela de 24h fechada).
     * Variáveis do template: {{1}} tipo de alerta, {{2}} cliente, {{3}} resumo, {{4}} link:
     * painel para a Alexsandra (distribui) e link curto wa.me do cliente para os demais (atendem).
     */
    private function sendTemplateAlert(Lead $lead, array $recipient): array
    {
        $phone = $recipient['phone'];
        $telefone = $lead->telefone ? " ({$lead->telefone})" : '';
        $cliente = $this->compactText($lead->nome . $telefone, 120);

        $contexto = trim($this->extractPropertyInterest($lead) . ' ' . $this->extractOriginalMessage($lead));
        $resumo = $contexto !== '' ? $this->compactText($contexto, 250) : 'Sem detalhes adicionais.';

        $actionUrl = rtrim((string) config('app.url'), '/') . "/distribuicao?leadId={$lead->id}";

        if ($recipient['name'] !== 'Alexsandra') {
            $clientPhone = $this->leadService->normalizePhone($lead->telefone);
            $clientPhoneDigits = $clientPhone ? ltrim($clientPhone, '+') : null;

            if ($clientPhoneDigits) {
                $actionUrl = $this->buildShortWaLink($lead, $clientPhoneDigits, $recipient['greeting'], $recipient['name']);
            }
        }

        try {
            $template = $this->assignmentNotificationService->operationalTemplateOrDefault((int) $lead->tenant_id);

            return $this->assignmentNotificationService->sendWhatsAppTemplate(
                (int) $lead->tenant_id,
                $phone,
                $template['name'],
                $template['language'],
                [$this->alertTypeLabel($lead), $cliente, $resumo, $actionUrl]
            );
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function alertTypeLabel(Lead $lead): string
    {
        $observacoes = (string) $lead->observacoes;

        if (stripos($observacoes, 'Chaves na') !== false) {
            return 'Novo lead - Chaves na Mão';
        }

        if (stripos($observacoes, 'mascote do portal') !== false) {
            return 'Novo lead - Mascote do Portal';
        }

        return 'Novo lead';
    }

    private function compactText(string $text, int $limit): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text !== '' ? mb_substr($text, 0, $limit) : '';
    }

    public function buildMessage(Lead $lead, array $recipient): string
    {
        $interesse = $this->extractPropertyInterest($lead);
        $mensagemOriginal = $this->extractOriginalMessage($lead);
        $clientPhone = $this->leadService->normalizePhone($lead->telefone);
        $clientPhoneDigits = $clientPhone ? ltrim($clientPhone, '+') : null;

        $lines = ['📥 Novo contato via Chaves na Mão'];
        $lines[] = "Cliente: {$lead->nome}";

        if ($interesse !== '') {
            $lines[] = "Cliente ficou interessado no imóvel: {$interesse}";
        }

        if ($mensagemOriginal !== '') {
            $lines[] = "Mensagem: {$mensagemOriginal}";
        }

        if ($clientPhoneDigits) {
            $link = $this->buildShortWaLink($lead, $clientPhoneDigits, $recipient['greeting'], $recipient['name']);
            $lines[] = "Falar com o cliente: {$link}";
        }

        return implode("\n", $lines);
    }

    /**
     * Monta o contexto do imóvel de interesse do lead (título/tipo do anúncio no
     * Chaves na Mão, com referência quando disponível; senão usa os campos
     * estruturados do lead - tipo, bairro e orçamento - como fallback).
     */
    public function extractPropertyInterest(Lead $lead): string
    {
        $observacoes = (string) $lead->observacoes;

        $titulo = null;
        if (preg_match('/📝\s*Anúncio:\s*(.+?)(?=\n|$)/u', $observacoes, $matches) && trim($matches[1]) !== '') {
            $titulo = trim($matches[1]);
        } elseif (preg_match('/🏠\s*Imóvel:\s*(.+?)(?=\n|$)/u', $observacoes, $matches) && trim($matches[1]) !== '') {
            $titulo = trim($matches[1]);
        }

        $referencia = null;
        if (preg_match('/📋\s*Referência:\s*(.+?)(?=\n|$)/u', $observacoes, $matches) && trim($matches[1]) !== '') {
            $referencia = trim($matches[1]);
        } elseif (!empty($lead->observacoes_cliente) && preg_match('/Refer[êe]ncia:\s*(.+)/iu', (string) $lead->observacoes_cliente, $matches)) {
            $referencia = trim($matches[1]);
        }

        if ($titulo) {
            return $referencia ? "{$titulo} (Ref: {$referencia})" : $titulo;
        }

        $criterios = array_filter([
            $lead->preferencia_tipo_imovel,
            $lead->preferencia_bairro ?: $lead->localizacao,
            $lead->budget_max ? 'até R$ ' . number_format((float) $lead->budget_max, 0, ',', '.') : null,
        ]);

        if (!empty($criterios)) {
            return implode(', ', $criterios) . ($referencia ? " (Ref: {$referencia})" : '');
        }

        return $referencia ?? '';
    }

    /**
     * Cria um link curto (dominio.com/hl/{code}) que redireciona para wa.me com a
     * saudação já preenchida, em vez de expor o número do cliente e o texto na mensagem.
     */
    private function buildShortWaLink(Lead $lead, string $clientPhoneDigits, string $greeting, string $recipientName): string
    {
        $tenantId = (int) $lead->tenant_id;
        $code = $this->generateUniqueCode($tenantId);

        LeadHandoffLink::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'code' => $code,
            'client_phone' => $clientPhoneDigits,
            'message' => $greeting,
            'recipient_name' => $recipientName,
        ]);

        return rtrim((string) config('app.url'), '/') . '/hl/' . $code;
    }

    private function generateUniqueCode(int $tenantId): string
    {
        do {
            $code = Str::lower(Str::random(7));
        } while (LeadHandoffLink::where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }

    /**
     * Extrai a mensagem/contexto do lead a partir das observações. Reconhece o formato
     * do ChavesNaMaoWebhookController::buildObservacoes() ("💬 Mensagem: ...") e, como
     * fallback, o formato do mascote do portal (PortalController::createChatLead(),
     * "[Chat Portal dd/mm/yyyy HH:mm] {texto}").
     */
    public function extractOriginalMessage(Lead $lead): string
    {
        $observacoes = trim((string) $lead->observacoes);

        if (preg_match('/💬\s*Mensagem:\s*(.+?)(?=\n[🔗🚗🏠]|$)/us', $observacoes, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/^\[Chat Portal[^\]]*\]\s*(.+)$/us', $observacoes, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
