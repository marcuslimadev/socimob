<?php

namespace App\Services;

use App\Models\ClientIntention;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class IntentionService
{
    /**
     * Criar intenção
     */
    public function create(Tenant $tenant, array $data): ClientIntention
    {
        $data['tenant_id'] = $tenant->id;

        $intention = ClientIntention::create($data);

        Log::info('Client intention created', [
            'tenant_id' => $tenant->id,
            'intention_id' => $intention->id,
            'type' => $data['type'],
        ]);

        return $intention;
    }

    /**
     * Atualizar intenção
     */
    public function update(ClientIntention $intention, array $data): ClientIntention
    {
        $intention->update($data);

        Log::info('Client intention updated', [
            'intention_id' => $intention->id,
        ]);

        return $intention;
    }

    /**
     * Deletar intenção
     */
    public function delete(ClientIntention $intention): bool
    {
        return $intention->delete();
    }

    /**
     * Pausar intenção
     */
    public function pause(ClientIntention $intention): ClientIntention
    {
        $intention->pause();

        Log::info('Client intention paused', [
            'intention_id' => $intention->id,
        ]);

        return $intention;
    }

    /**
     * Retomar intenção
     */
    public function resume(ClientIntention $intention): ClientIntention
    {
        $intention->resume();

        Log::info('Client intention resumed', [
            'intention_id' => $intention->id,
        ]);

        return $intention;
    }

    /**
     * Completar intenção
     */
    public function complete(ClientIntention $intention): ClientIntention
    {
        $intention->complete();

        Log::info('Client intention completed', [
            'intention_id' => $intention->id,
        ]);

        return $intention;
    }

    /**
     * Cancelar intenção
     */
    public function cancel(ClientIntention $intention): ClientIntention
    {
        $intention->cancel();

        Log::info('Client intention canceled', [
            'intention_id' => $intention->id,
        ]);

        return $intention;
    }

    /**
     * Buscar imóveis que combinam com intenção
     */
    public function findMatchingProperties(ClientIntention $intention): array
    {
        $query = Property::forTenant($intention->tenant_id)
            ->where('active', true);

        // Filtrar por tipo
        $query->where('finalidade_imovel', $intention->type);

        // Filtrar por quartos
        if ($intention->min_bedrooms) {
            $query->where('quartos', '>=', $intention->min_bedrooms);
        }
        if ($intention->max_bedrooms) {
            $query->where('quartos', '<=', $intention->max_bedrooms);
        }

        // Filtrar por banheiros
        if ($intention->min_bathrooms) {
            $query->where('banheiros', '>=', $intention->min_bathrooms);
        }
        if ($intention->max_bathrooms) {
            $query->where('banheiros', '<=', $intention->max_bathrooms);
        }

        // Filtrar por preço
        if ($intention->min_price) {
            $query->where('preco', '>=', $intention->min_price);
        }
        if ($intention->max_price) {
            $query->where('preco', '<=', $intention->max_price);
        }

        // Filtrar por área
        if ($intention->min_area) {
            $query->where('area', '>=', $intention->min_area);
        }
        if ($intention->max_area) {
            $query->where('area', '<=', $intention->max_area);
        }

        // Filtrar por cidade
        if ($intention->city) {
            $query->where('cidade', $intention->city);
        }

        // Filtrar por bairros
        if ($intention->neighborhoods && count($intention->neighborhoods) > 0) {
            $query->whereIn('bairro', $intention->neighborhoods);
        }

        return $query->get()->toArray();
    }

    /**
     * Notificar sobre imóvel que combina
     */
    public function notifyPropertyMatch(ClientIntention $intention, Property $property): void
    {
        // Verificar se já foi notificado sobre este imóvel
        $existingNotification = Notification::where('intention_id', $intention->id)
            ->where('property_id', $property->id)
            ->where('type', 'property_match')
            ->first();

        if ($existingNotification) {
            return;
        }

        // Criar notificação
        $notification = Notification::create([
            'tenant_id' => $intention->tenant_id,
            'user_id' => $intention->client_id,
            'intention_id' => $intention->id,
            'property_id' => $property->id,
            'type' => 'property_match',
            'title' => 'Imóvel Encontrado!',
            'message' => "Encontramos um {$property->tipo_imovel} que combina com sua intenção de {$intention->getFormattedType()}!",
            'action_url' => "/property/{$property->id}",
            'data' => [
                'property_id' => $property->id,
                'property_title' => $property->titulo,
                'property_price' => $property->preco,
            ],
            'channel' => 'in_app',
            'is_read' => false,
            'is_sent' => false,
        ]);

        // Se cliente deseja notificação por email
        if ($intention->notify_by_email && $intention->email) {
            $this->sendEmailNotification($notification);
        }

        // Se cliente deseja notificação por WhatsApp
        if ($intention->notify_by_whatsapp && $intention->whatsapp) {
            $this->sendWhatsAppNotification($notification);
        }

        // Se cliente deseja notificação por SMS
        if ($intention->notify_by_sms && $intention->phone) {
            $this->sendSMSNotification($notification);
        }

        Log::info('Property match notification created', [
            'intention_id' => $intention->id,
            'property_id' => $property->id,
            'notification_id' => $notification->id,
        ]);
    }

    /**
     * Enviar notificação por email
     */
    private function sendEmailNotification(Notification $notification): void
    {
        try {
            // TODO: Implementar envio de email
            // Mail::send(new PropertyMatchMail($notification));

            $notification->markAsSent();

            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            $notification->recordSendAttempt($e->getMessage());

            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar notificação por WhatsApp
     */
    private function sendWhatsAppNotification(Notification $notification): void
    {
        try {
            // TODO: Implementar envio de WhatsApp
            // WhatsAppService::send($notification->intention->whatsapp, $notification->message);

            $notification->markAsSent();

            Log::info('WhatsApp notification sent', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            $notification->recordSendAttempt($e->getMessage());

            Log::error('Failed to send WhatsApp notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar notificação por SMS
     */
    private function sendSMSNotification(Notification $notification): void
    {
        try {
            // TODO: Implementar envio de SMS
            // SMSService::send($notification->intention->phone, $notification->message);

            $notification->markAsSent();

            Log::info('SMS notification sent', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            $notification->recordSendAttempt($e->getMessage());

            Log::error('Failed to send SMS notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Processar notificações pendentes
     */
    public function processPendingNotifications(): int
    {
        $notifications = Notification::readyToSend()
            ->where('send_attempts', '<', 3)
            ->limit(100)
            ->get();

        $processed = 0;

        foreach ($notifications as $notification) {
            try {
                match($notification->channel) {
                    'email' => $this->sendEmailNotification($notification),
                    'whatsapp' => $this->sendWhatsAppNotification($notification),
                    'sms' => $this->sendSMSNotification($notification),
                    'in_app' => $notification->markAsSent(),
                    default => null,
                };

                $processed++;
            } catch (\Exception $e) {
                Log::error('Error processing notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Obter estatísticas de intenção
     */
    public function getStats(ClientIntention $intention): array
    {
        $matchingProperties = $this->findMatchingProperties($intention);
        $notifications = $intention->notifications()->get();

        return [
            'matching_properties_count' => count($matchingProperties),
            'notifications_count' => $notifications->count(),
            'unread_notifications_count' => $notifications->where('is_read', false)->count(),
            'status' => $intention->getFormattedStatus(),
            'created_at' => $intention->created_at,
            'updated_at' => $intention->updated_at,
        ];
    }
}
