<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * Serviço de Notificações Multi-Canal
 */
class NotificationService
{
    private $twilioService;
    private $whatsappService;

    public function __construct(
        TwilioService $twilioService = null,
        WhatsAppService $whatsappService = null
    ) {
        $this->twilioService = $twilioService;
        $this->whatsappService = $whatsappService;
    }

    /**
     * Enviar notificação para usuário
     *
     * @param int $userId
     * @param array $notificationData
     * @param array $channels ['in_app', 'email', 'sms', 'whatsapp', 'push']
     * @return array
     */
    public function sendToUser(int $userId, array $notificationData, array $channels = ['in_app']): array
    {
        try {
            $user = User::find($userId);

            if (!$user) {
                throw new \Exception('User not found');
            }

            $results = [];

            foreach ($channels as $channel) {
                $notification = $this->createNotification($userId, $notificationData, $channel);

                try {
                    switch ($channel) {
                        case 'in_app':
                            $result = $this->sendInApp($notification);
                            break;

                        case 'email':
                            $result = $this->sendEmail($notification, $user);
                            break;

                        case 'sms':
                            // SMS desabilitado
                            $result = ['success' => false, 'message' => 'SMS desabilitado'];
                            break;

                        case 'whatsapp':
                            $result = $this->sendWhatsApp($notification, $user);
                            break;

                        case 'push':
                            $result = $this->sendPush($notification, $user);
                            break;

                        default:
                            $result = ['success' => false, 'message' => 'Unknown channel'];
                    }

                    $results[$channel] = $result;

                } catch (\Exception $e) {
                    $results[$channel] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];

                    $notification->recordSendAttempt($e->getMessage());
                }
            }

            return [
                'success' => true,
                'notification_id' => $notification->id,
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Error sending notification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enviar notificação para múltiplos usuários
     *
     * @param array $userIds
     * @param array $notificationData
     * @param array $channels
     * @return array
     */
    public function sendToMultipleUsers(array $userIds, array $notificationData, array $channels = ['in_app']): array
    {
        $results = [];

        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendToUser($userId, $notificationData, $channels);
        }

        return [
            'success' => true,
            'total' => count($userIds),
            'results' => $results
        ];
    }

    /**
     * Enviar notificação para todos admins do tenant
     *
     * @param int $tenantId
     * @param array $notificationData
     * @param array $channels
     * @return array
     */
    public function sendToTenantAdmins(int $tenantId, array $notificationData, array $channels = ['in_app']): array
    {
        $admins = User::where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();

        if ($admins->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No active admins found'
            ];
        }

        return $this->sendToMultipleUsers($admins->pluck('id')->toArray(), $notificationData, $channels);
    }

    /**
     * Criar notificação no banco
     *
     * @param int $userId
     * @param array $data
     * @param string $channel
     * @return Notification
     */
    private function createNotification(int $userId, array $data, string $channel): Notification
    {
        return Notification::create([
            'tenant_id' => $data['tenant_id'] ?? app('tenant')?->id,
            'user_id' => $userId,
            'intention_id' => $data['intention_id'] ?? null,
            'property_id' => $data['property_id'] ?? null,
            'type' => $data['type'] ?? 'general',
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'data' => $data['data'] ?? null,
            'channel' => $channel,
            'is_read' => false,
            'is_sent' => false,
        ]);
    }

    /**
     * Enviar notificação in-app
     */
    private function sendInApp(Notification $notification): array
    {
        $notification->markAsSent();

        return [
            'success' => true,
            'channel' => 'in_app'
        ];
    }

    /**
     * Enviar notificação por email
     */
    private function sendEmail(Notification $notification, User $user): array
    {
        try {
            $tenant = $user->tenant;
            $fromEmail = $tenant?->contact_email ?? env('MAIL_FROM_ADDRESS', 'noreply@socimob.com');
            $fromName = $tenant?->name ?? env('MAIL_FROM_NAME', 'SOCIMOB');

            Mail::send('emails.notification', [
                'user' => $user,
                'notification' => $notification,
                'title' => $notification->title,
                'message' => $notification->message,
                'action_url' => $notification->action_url,
            ], function($message) use ($user, $notification, $fromEmail, $fromName) {
                $message->to($user->email, $user->name)
                    ->from($fromEmail, $fromName)
                    ->subject($notification->title);
            });

            $notification->markAsSent();

            return [
                'success' => true,
                'channel' => 'email'
            ];

        } catch (\Exception $e) {
            Log::error('Error sending email notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Enviar notificação por SMS
     */
    private function sendSMS(Notification $notification, User $user): array
    {
        try {
            if (!$this->twilioService) {
                throw new \Exception('Twilio service not available');
            }

            if (!$user->telefone && !$user->whatsapp) {
                throw new \Exception('User has no phone number');
            }

            $phone = $user->whatsapp ?? $user->telefone;

            // Mensagem SMS (limitada a 160 caracteres)
            $message = mb_substr($notification->message, 0, 160);

            $result = $this->twilioService->sendSMS($phone, $message);

            if ($result) {
                $notification->markAsSent();

                return [
                    'success' => true,
                    'channel' => 'sms'
                ];
            }

            throw new \Exception('SMS sending failed');

        } catch (\Exception $e) {
            Log::error('Error sending SMS notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Enviar notificação por WhatsApp
     */
    private function sendWhatsApp(Notification $notification, User $user): array
    {
        try {
            if (!$this->twilioService) {
                throw new \Exception('Twilio service not available');
            }

            if (!$user->whatsapp) {
                throw new \Exception('User has no WhatsApp number');
            }

            $message = $notification->message;

            if ($notification->action_url) {
                $message .= "\n\n🔗 " . $notification->action_url;
            }

            $result = $this->twilioService->sendMessage($user->whatsapp, $message);

            if ($result) {
                $notification->markAsSent();

                return [
                    'success' => true,
                    'channel' => 'whatsapp'
                ];
            }

            throw new \Exception('WhatsApp sending failed');

        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Enviar notificação push
     */
    private function sendPush(Notification $notification, User $user): array
    {
        try {
            // TODO: Implementar push notifications usando FCM ou similar
            Log::info('Push notification not implemented yet', [
                'notification_id' => $notification->id,
                'user_id' => $user->id
            ]);

            return [
                'success' => false,
                'channel' => 'push',
                'message' => 'Push notifications not implemented yet'
            ];

        } catch (\Exception $e) {
            Log::error('Error sending push notification', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Marcar notificações como lidas
     *
     * @param int $userId
     * @param array $notificationIds
     * @return int
     */
    public function markAsRead(int $userId, array $notificationIds = []): int
    {
        $query = Notification::where('user_id', $userId)
            ->where('is_read', false);

        if (!empty($notificationIds)) {
            $query->whereIn('id', $notificationIds);
        }

        $count = $query->update([
            'is_read' => true,
            'read_at' => Carbon::now()
        ]);

        Log::info('Notifications marked as read', [
            'user_id' => $userId,
            'count' => $count
        ]);

        return $count;
    }

    /**
     * Obter notificações não lidas de um usuário
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUnreadNotifications(int $userId, int $limit = 20): array
    {
        $notifications = Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'count' => $notifications->count(),
            'notifications' => $notifications->toArray()
        ];
    }

    /**
     * Obter contador de notificações não lidas
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Processar fila de notificações pendentes
     *
     * @return array
     */
    public function processQueue(): array
    {
        $pending = Notification::readyToSend()
            ->limit(100)
            ->get();

        $processed = 0;
        $errors = 0;

        foreach ($pending as $notification) {
            try {
                $user = $notification->user;

                if (!$user) {
                    $notification->recordSendAttempt('User not found');
                    $errors++;
                    continue;
                }

                $result = match($notification->channel) {
                    'email' => $this->sendEmail($notification, $user),
                    'sms' => $this->sendSMS($notification, $user),
                    'whatsapp' => $this->sendWhatsApp($notification, $user),
                    'push' => $this->sendPush($notification, $user),
                    default => ['success' => false, 'message' => 'Unknown channel']
                };

                if ($result['success']) {
                    $processed++;
                } else {
                    $errors++;
                }

            } catch (\Exception $e) {
                $errors++;
                Log::error('Error processing notification queue', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => true,
            'processed' => $processed,
            'errors' => $errors,
            'total' => $pending->count()
        ];
    }

    /**
     * Criar notificação de novo lead
     *
     * @param int $leadId
     * @param int $tenantId
     * @return array
     */
    public function notifyNewLead(int $leadId, int $tenantId): array
    {
        $lead = \App\Models\Lead::find($leadId);

        if (!$lead) {
            return ['success' => false, 'message' => 'Lead not found'];
        }

        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'new_lead',
            'title' => 'Novo Lead Recebido',
            'message' => "Novo lead: {$lead->nome} ({$lead->telefone})",
            'action_url' => "/leads/{$leadId}",
            'data' => ['lead_id' => $leadId]
        ];

        return $this->sendToTenantAdmins($tenantId, $notificationData, ['in_app', 'whatsapp']);
    }

    /**
     * Criar notificação de novo interesse em imóvel
     *
     * @param int $propertyId
     * @param int $leadId
     * @param int $tenantId
     * @return array
     */
    public function notifyPropertyInterest(int $propertyId, int $leadId, int $tenantId): array
    {
        $property = \App\Models\Property::find($propertyId);
        $lead = \App\Models\Lead::find($leadId);

        if (!$property || !$lead) {
            return ['success' => false, 'message' => 'Property or Lead not found'];
        }

        $notificationData = [
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'type' => 'property_interest',
            'title' => 'Interesse em Imóvel',
            'message' => "{$lead->nome} demonstrou interesse no imóvel {$property->titulo}",
            'action_url' => "/properties/{$propertyId}",
            'data' => [
                'property_id' => $propertyId,
                'lead_id' => $leadId
            ]
        ];

        return $this->sendToTenantAdmins($tenantId, $notificationData, ['in_app']);
    }

    /**
     * Criar notificacao de nova solicitacao de vistoria
     *
     * @param int $vistoriaId
     * @param int $tenantId
     * @param array $context
     * @return array
     */
    public function notifyVistoriaRequested(int $vistoriaId, int $tenantId, array $context = []): array
    {
        $cliente = $context['cliente'] ?? null;
        $imovel = $context['imovel'] ?? null;
        $descricao = trim(($cliente ? $cliente : '') . ($imovel ? ($cliente ? ' - ' : '') . $imovel : ''));

        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'vistoria_solicitada',
            'title' => 'Nova solicitacao de vistoria',
            'message' => $descricao ? "Nova solicitacao de vistoria: {$descricao}" : "Nova solicitacao de vistoria #{$vistoriaId}",
            'action_url' => "/vistorias/{$vistoriaId}",
            'data' => array_merge(['vistoria_id' => $vistoriaId], $context),
        ];

        return $this->sendToTenantAdmins($tenantId, $notificationData, ['in_app']);
    }

    /**
     * Criar notificacao de vistoria designada
     *
     * @param int $vistoriaId
     * @param int $tenantId
     * @param int $userId
     * @param array $context
     * @param array $channels
     * @return array
     */
    public function notifyVistoriaAssigned(
        int $vistoriaId,
        int $tenantId,
        int $userId,
        array $context = [],
        array $channels = ['in_app']
    ): array {
        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'vistoria_designada',
            'title' => 'Vistoria designada',
            'message' => $context['message'] ?? "Voce foi designado para a vistoria #{$vistoriaId}",
            'action_url' => "/vistorias/{$vistoriaId}",
            'data' => array_merge(['vistoria_id' => $vistoriaId], $context),
        ];

        return $this->sendToUser($userId, $notificationData, $channels);
    }

    /**
     * Criar notificacao de vistoria concluida
     *
     * @param int $vistoriaId
     * @param int $tenantId
     * @param array $context
     * @return array
     */
    public function notifyVistoriaCompleted(int $vistoriaId, int $tenantId, array $context = []): array
    {
        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'vistoria_concluida',
            'title' => 'Vistoria concluida',
            'message' => $context['message'] ?? "A vistoria #{$vistoriaId} foi concluida",
            'action_url' => "/vistorias/{$vistoriaId}",
            'data' => array_merge(['vistoria_id' => $vistoriaId], $context),
        ];

        return $this->sendToTenantAdmins($tenantId, $notificationData, ['in_app']);
    }

    /**
     * Criar notificacao de assinatura enviada
     *
     * @param int $documentId
     * @param int $tenantId
     * @param int $userId
     * @param array $context
     * @param array $channels
     * @return array
     */
    public function notifySignatureRequested(
        int $documentId,
        int $tenantId,
        int $userId,
        array $context = [],
        array $channels = ['in_app']
    ): array {
        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'assinatura_enviada',
            'title' => 'Assinatura solicitada',
            'message' => $context['message'] ?? "Documento #{$documentId} aguardando assinatura",
            'action_url' => "/assinaturas/{$documentId}",
            'data' => array_merge(['documento_id' => $documentId], $context),
        ];

        return $this->sendToUser($userId, $notificationData, $channels);
    }

    /**
     * Criar notificacao de assinatura concluida
     *
     * @param int $documentId
     * @param int $tenantId
     * @param array $context
     * @return array
     */
    public function notifySignatureCompleted(int $documentId, int $tenantId, array $context = []): array
    {
        $notificationData = [
            'tenant_id' => $tenantId,
            'type' => 'assinatura_assinada',
            'title' => 'Assinatura concluida',
            'message' => $context['message'] ?? "Documento #{$documentId} foi assinado",
            'action_url' => "/assinaturas/{$documentId}",
            'data' => array_merge(['documento_id' => $documentId], $context),
        ];

        return $this->sendToTenantAdmins($tenantId, $notificationData, ['in_app']);
    }
}
