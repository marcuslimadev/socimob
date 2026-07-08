<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadHandoffLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifica Alexsandra, Roberto e Marcus por WhatsApp sobre leads do Chaves na Mão
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
        [
            'name' => 'Marcus',
            'phone' => '5592992287144',
            'greeting' => 'Oi, sou o Marcus, recebi seu contato e estou disponível para iniciar seu atendimento.',
        ],
    ];

    public function __construct(
        private ConversationAssignmentNotificationService $assignmentNotificationService,
        private LeadService $leadService
    ) {
    }

    /**
     * Envia o handoff (mensagem do cliente + link wa.me) para Alexsandra e Roberto.
     * Marca no lead quem foi notificado e quando. Retorna os nomes notificados com sucesso.
     */
    public function notify(Lead $lead): array
    {
        $notifiedTo = [];

        foreach (self::RECIPIENTS as $recipient) {
            $result = $this->sendHandoffMessage($lead, $recipient);

            if ($result['success'] ?? false) {
                $notifiedTo[] = $recipient['name'];
            }
        }

        if (!empty($notifiedTo)) {
            $lead->updateQuietly([
                'status' => 'em_atendimento',
                'atendimento_notificado_em' => now(),
                'atendimento_notificado_para' => implode(', ', $notifiedTo),
            ]);

            Log::info('[LeadHandoffNotificationService] Lead do Chaves na Mão notificado para atendimento humano', [
                'lead_id' => $lead->id,
                'notificado_para' => $notifiedTo,
            ]);
        }

        return $notifiedTo;
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

        $this->sendReportAndPendingHandoffs($tenantId, $recipient);

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

    private function sendReportAndPendingHandoffs(int $tenantId, array $recipient): void
    {
        $this->assignmentNotificationService->sendWhatsAppMessage(
            $tenantId,
            $recipient['phone'],
            $this->buildReportMessage($tenantId)
        );

        $pendentes = $this->pendingLeads($tenantId);

        if ($pendentes->isEmpty()) {
            $this->assignmentNotificationService->sendWhatsAppMessage(
                $tenantId,
                $recipient['phone'],
                'Nenhum lead do Chaves na Mão pendente de atendimento no momento. ✅'
            );
            return;
        }

        foreach ($pendentes as $lead) {
            $this->sendHandoffMessage($lead, $recipient);
        }
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

    public function buildMessage(Lead $lead, array $recipient): string
    {
        $mensagemOriginal = $this->extractOriginalMessage($lead);
        $clientPhone = $this->leadService->normalizePhone($lead->telefone);
        $clientPhoneDigits = $clientPhone ? ltrim($clientPhone, '+') : null;

        $lines = ['📥 Novo contato via Chaves na Mão'];
        $lines[] = "Cliente: {$lead->nome}";

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
     * Extrai a mensagem original enviada pelo cliente a partir das observações
     * montadas pelo ChavesNaMaoWebhookController::buildObservacoes().
     */
    public function extractOriginalMessage(Lead $lead): string
    {
        $observacoes = (string) $lead->observacoes;

        if (preg_match('/💬\s*Mensagem:\s*(.+?)(?=\n[🔗🚗🏠]|$)/us', $observacoes, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }
}
