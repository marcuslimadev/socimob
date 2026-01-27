<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Mensagem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeadConversationService
{
    private WhatsAppService $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function ensureConversaForLead(Lead $lead, array $options = []): ?Conversa
    {
        try {
            $existing = $lead->conversas()->orderBy('id', 'desc')->first();
            if ($existing) {
                return $existing;
            }

            $telefone = $this->normalizeTelefone($lead->telefone ?? $lead->whatsapp);
            if (!$telefone) {
                Log::warning('Lead without phone number, cannot create conversation', [
                    'lead_id' => $lead->id,
                    'lead_email' => $lead->email,
                    'tenant_id' => $lead->tenant_id,
                ]);
                return null;
            }

            $iniciadaEm = $lead->created_at ? Carbon::parse($lead->created_at) : Carbon::now();
            $ultimaAtividade = $lead->updated_at ? Carbon::parse($lead->updated_at) : $iniciadaEm;

            $conversa = Conversa::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'corretor_id' => $lead->corretor_id,
                'telefone' => $telefone,
                'status' => 'ativa',
                'canal' => $options['canal'] ?? 'chaves_na_mao',
                'iniciada_em' => $iniciadaEm,
                'ultima_atividade' => $ultimaAtividade,
            ]);

            $message = $options['message'] ?? $this->extractMensagemFromObservacoes($lead->observacoes);
            if ($message) {
                Mensagem::create([
                    'tenant_id' => $lead->tenant_id,
                    'conversa_id' => $conversa->id,
                    'direction' => 'incoming',
                    'message_type' => 'text',
                    'content' => $message,
                    'status' => 'received',
                    'sent_at' => $iniciadaEm,
                ]);

                $conversa->update(['ultima_atividade' => $iniciadaEm]);
            }

            Log::info('Conversation created for lead', [
                'lead_id' => $lead->id,
                'conversa_id' => $conversa->id,
                'canal' => $conversa->canal,
            ]);

            return $conversa;

        } catch (\Exception $e) {
            Log::error('Error creating conversation for lead', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Tentar novamente após 5 segundos se for erro de conexão com DB
            if (str_contains($e->getMessage(), 'database') || str_contains($e->getMessage(), 'connection')) {
                sleep(5);
                try {
                    return $this->ensureConversaForLead($lead, array_merge($options, ['_retry' => true]));
                } catch (\Exception $retryError) {
                    Log::error('Retry failed for conversation creation', [
                        'lead_id' => $lead->id,
                        'error' => $retryError->getMessage()
                    ]);
                }
            }

            return null;
        }
    }

    public function startAiForLead(Lead $lead, array $options = []): array
    {
        $conversa = $this->ensureConversaForLead($lead, $options);
        if (!$conversa) {
            return [
                'success' => false,
                'message' => 'Lead não possui telefone para iniciar atendimento'
            ];
        }

        if ($conversa->corretor_id) {
            return [
                'success' => false,
                'message' => 'Atendimento humano já iniciado para este lead'
            ];
        }

        $hasOutgoingMessages = $conversa->mensagens()
            ->where('direction', 'outgoing')
            ->exists();

        if ($hasOutgoingMessages) {
            return [
                'success' => false,
                'message' => 'A IA já iniciou o atendimento para este lead'
            ];
        }

        $mensagemInicial = $options['message'] ?? $this->extractMensagemFromObservacoes($lead->observacoes);
        $usarTemplate = $options['usar_template'] ?? $lead->isFromIntegration();

        $result = $this->whatsappService->initiateAiConversation($conversa, $lead, $mensagemInicial, [
            'usar_template' => $usarTemplate,
        ]);

        return [
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Falha ao iniciar atendimento',
            'conversa_id' => $conversa->id,
        ];
    }

    private function normalizeTelefone(?string $telefone): ?string
    {
        if (!$telefone) {
            return null;
        }

        return trim($telefone);
    }

    private function extractMensagemFromObservacoes(?string $observacoes): ?string
    {
        if (!$observacoes) {
            return null;
        }

        $texto = str_replace(["\r\n", "\r"], "\n", $observacoes);
        $linhas = explode("\n", $texto);

        $capturando = false;
        $buffer = [];
        foreach ($linhas as $linha) {
            $linhaTrim = trim($linha);
            if (!$capturando && stripos($linhaTrim, 'Mensagem:') !== false) {
                $capturando = true;
                $buffer[] = trim(preg_replace('/^.*?Mensagem:\s*/i', '', $linhaTrim));
                continue;
            }

            if ($capturando) {
                if ($linhaTrim === '') {
                    break;
                }

                if ($this->isObservacaoMarker($linhaTrim)) {
                    break;
                }

                $buffer[] = $linhaTrim;
            }
        }

        $mensagem = trim(implode("\n", $buffer));
        return $mensagem !== '' ? $mensagem : null;
    }

    private function isObservacaoMarker(string $linha): bool
    {
        $markers = ['Origem:', 'Imov', 'Veic', 'Anunc', 'Referenc'];
        foreach ($markers as $marker) {
            if (stripos($linha, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obter histórico completo de mensagens de uma conversa
     *
     * @param int $conversaId
     * @param array $options
     * @return array
     */
    public function getMessageHistory(int $conversaId, array $options = []): array
    {
        try {
            $conversa = Conversa::with(['lead', 'corretor'])->findOrFail($conversaId);

            $query = Mensagem::where('conversa_id', $conversaId)
                ->orderBy('sent_at', 'asc');

            // Filtros opcionais
            if (isset($options['direction'])) {
                $query->where('direction', $options['direction']);
            }

            if (isset($options['message_type'])) {
                $query->where('message_type', $options['message_type']);
            }

            if (isset($options['limit'])) {
                $query->limit($options['limit']);
            }

            if (isset($options['offset'])) {
                $query->offset($options['offset']);
            }

            $mensagens = $query->get();

            return [
                'success' => true,
                'conversa' => [
                    'id' => $conversa->id,
                    'lead_id' => $conversa->lead_id,
                    'lead_nome' => $conversa->lead->nome ?? 'Desconhecido',
                    'corretor_nome' => $conversa->corretor->name ?? 'IA',
                    'status' => $conversa->status,
                    'canal' => $conversa->canal,
                    'iniciada_em' => $conversa->iniciada_em?->toIso8601String(),
                    'ultima_atividade' => $conversa->ultima_atividade?->toIso8601String(),
                ],
                'mensagens' => $mensagens->map(function($msg) {
                    return [
                        'id' => $msg->id,
                        'direction' => $msg->direction,
                        'message_type' => $msg->message_type,
                        'content' => $msg->content,
                        'media_url' => $msg->media_url,
                        'status' => $msg->status,
                        'sent_at' => $msg->sent_at?->toIso8601String(),
                        'read_at' => $msg->read_at?->toIso8601String(),
                    ];
                })->toArray(),
                'total' => $mensagens->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching message history', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao buscar histórico de mensagens',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Adicionar mensagem a uma conversa
     *
     * @param int $conversaId
     * @param array $messageData
     * @return array
     */
    public function addMessage(int $conversaId, array $messageData): array
    {
        try {
            $conversa = Conversa::findOrFail($conversaId);

            $mensagem = Mensagem::create([
                'tenant_id' => $conversa->tenant_id,
                'conversa_id' => $conversaId,
                'message_sid' => $messageData['message_sid'] ?? null,
                'direction' => $messageData['direction'] ?? 'outgoing',
                'message_type' => $messageData['message_type'] ?? 'text',
                'content' => $messageData['content'] ?? null,
                'media_url' => $messageData['media_url'] ?? null,
                'transcription' => $messageData['transcription'] ?? null,
                'status' => $messageData['status'] ?? 'sent',
                'sent_at' => $messageData['sent_at'] ?? Carbon::now(),
            ]);

            // Atualizar última atividade da conversa
            $conversa->update([
                'ultima_atividade' => Carbon::now()
            ]);

            // Atualizar última interação do lead
            if ($conversa->lead_id) {
                Lead::where('id', $conversa->lead_id)->update([
                    'ultima_interacao' => Carbon::now()
                ]);
            }

            Log::info('Message added to conversation', [
                'conversa_id' => $conversaId,
                'mensagem_id' => $mensagem->id,
                'direction' => $mensagem->direction,
            ]);

            return [
                'success' => true,
                'mensagem' => [
                    'id' => $mensagem->id,
                    'content' => $mensagem->content,
                    'status' => $mensagem->status,
                    'sent_at' => $mensagem->sent_at?->toIso8601String(),
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error adding message to conversation', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao adicionar mensagem',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Marcar mensagens como lidas
     *
     * @param int $conversaId
     * @param array $messageIds
     * @return array
     */
    public function markMessagesAsRead(int $conversaId, array $messageIds = []): array
    {
        try {
            $query = Mensagem::where('conversa_id', $conversaId)
                ->where('direction', 'incoming')
                ->whereNull('read_at');

            if (!empty($messageIds)) {
                $query->whereIn('id', $messageIds);
            }

            $updated = $query->update([
                'read_at' => Carbon::now()
            ]);

            Log::info('Messages marked as read', [
                'conversa_id' => $conversaId,
                'count' => $updated
            ]);

            return [
                'success' => true,
                'updated' => $updated
            ];

        } catch (\Exception $e) {
            Log::error('Error marking messages as read', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao marcar mensagens como lidas',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sincronizar mensagens com WhatsApp
     * (Estrutura preparada para implementação futura)
     *
     * @param int $conversaId
     * @return array
     */
    public function syncWithWhatsApp(int $conversaId): array
    {
        try {
            $conversa = Conversa::findOrFail($conversaId);

            // TODO: Implementar integração real com WhatsApp API
            // Possíveis opções:
            // 1. Twilio WhatsApp API
            // 2. WhatsApp Business API
            // 3. Evolution API
            // 4. Baileys (via websocket)

            Log::info('WhatsApp sync initiated (not implemented yet)', [
                'conversa_id' => $conversaId
            ]);

            return [
                'success' => true,
                'message' => 'Sincronização WhatsApp não implementada ainda',
                'conversa_id' => $conversaId,
                'pending_implementation' => true
            ];

        } catch (\Exception $e) {
            Log::error('Error syncing with WhatsApp', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao sincronizar com WhatsApp',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obter estatísticas de uma conversa
     *
     * @param int $conversaId
     * @return array
     */
    public function getConversationStats(int $conversaId): array
    {
        try {
            $conversa = Conversa::findOrFail($conversaId);

            $totalMessages = Mensagem::where('conversa_id', $conversaId)->count();
            $incomingMessages = Mensagem::where('conversa_id', $conversaId)
                ->where('direction', 'incoming')->count();
            $outgoingMessages = Mensagem::where('conversa_id', $conversaId)
                ->where('direction', 'outgoing')->count();
            $unreadMessages = Mensagem::where('conversa_id', $conversaId)
                ->where('direction', 'incoming')
                ->whereNull('read_at')->count();

            $firstMessage = Mensagem::where('conversa_id', $conversaId)
                ->orderBy('sent_at', 'asc')->first();
            $lastMessage = Mensagem::where('conversa_id', $conversaId)
                ->orderBy('sent_at', 'desc')->first();

            $responseTime = null;
            if ($firstMessage && $lastMessage && $firstMessage->id !== $lastMessage->id) {
                $responseTime = $firstMessage->sent_at->diffInMinutes($lastMessage->sent_at);
            }

            return [
                'success' => true,
                'stats' => [
                    'total_messages' => $totalMessages,
                    'incoming_messages' => $incomingMessages,
                    'outgoing_messages' => $outgoingMessages,
                    'unread_messages' => $unreadMessages,
                    'first_message_at' => $firstMessage?->sent_at?->toIso8601String(),
                    'last_message_at' => $lastMessage?->sent_at?->toIso8601String(),
                    'response_time_minutes' => $responseTime,
                    'status' => $conversa->status,
                    'duration_hours' => $conversa->iniciada_em
                        ? $conversa->iniciada_em->diffInHours(Carbon::now())
                        : 0,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error fetching conversation stats', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao buscar estatísticas',
                'error' => $e->getMessage()
            ];
        }
    }
}
