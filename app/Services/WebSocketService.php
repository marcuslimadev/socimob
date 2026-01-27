<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Serviço de WebSocket para notificações em tempo real
 * Usando Redis Pub/Sub como backend
 */
class WebSocketService
{
    /**
     * Publicar evento para um usuário específico
     *
     * @param int $userId
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function publishToUser(int $userId, string $event, array $data): bool
    {
        try {
            $channel = "user:{$userId}";

            $payload = json_encode([
                'event' => $event,
                'data' => $data,
                'timestamp' => time(),
            ]);

            // Publicar no Redis Pub/Sub
            Redis::publish($channel, $payload);

            Log::debug('WebSocket event published', [
                'user_id' => $userId,
                'event' => $event,
                'channel' => $channel
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error publishing WebSocket event', [
                'user_id' => $userId,
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Publicar evento para um tenant
     *
     * @param int $tenantId
     * @param string $event
     * @param array $data
     * @return bool
     */
    public function publishToTenant(int $tenantId, string $event, array $data): bool
    {
        try {
            $channel = "tenant:{$tenantId}";

            $payload = json_encode([
                'event' => $event,
                'data' => $data,
                'timestamp' => time(),
            ]);

            Redis::publish($channel, $payload);

            Log::debug('WebSocket event published to tenant', [
                'tenant_id' => $tenantId,
                'event' => $event
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Error publishing WebSocket event to tenant', [
                'tenant_id' => $tenantId,
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Publicar notificação em tempo real
     *
     * @param int $userId
     * @param array $notification
     * @return bool
     */
    public function publishNotification(int $userId, array $notification): bool
    {
        return $this->publishToUser($userId, 'notification', $notification);
    }

    /**
     * Publicar nova mensagem de chat
     *
     * @param int $conversaId
     * @param array $message
     * @return bool
     */
    public function publishChatMessage(int $conversaId, array $message): bool
    {
        try {
            $channel = "chat:{$conversaId}";

            $payload = json_encode([
                'event' => 'new_message',
                'data' => $message,
                'timestamp' => time(),
            ]);

            Redis::publish($channel, $payload);

            return true;

        } catch (\Exception $e) {
            Log::error('Error publishing chat message', [
                'conversa_id' => $conversaId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Publicar status de digitação
     *
     * @param int $conversaId
     * @param int $userId
     * @param bool $isTyping
     * @return bool
     */
    public function publishTypingStatus(int $conversaId, int $userId, bool $isTyping): bool
    {
        try {
            $channel = "chat:{$conversaId}";

            $payload = json_encode([
                'event' => 'typing',
                'data' => [
                    'user_id' => $userId,
                    'is_typing' => $isTyping
                ],
                'timestamp' => time(),
            ]);

            Redis::publish($channel, $payload);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Publicar atualização de lead
     *
     * @param int $tenantId
     * @param int $leadId
     * @param string $action (created, updated, deleted)
     * @param array $leadData
     * @return bool
     */
    public function publishLeadUpdate(int $tenantId, int $leadId, string $action, array $leadData): bool
    {
        return $this->publishToTenant($tenantId, 'lead_update', [
            'action' => $action,
            'lead_id' => $leadId,
            'lead' => $leadData
        ]);
    }

    /**
     * Publicar atualização de property
     *
     * @param int $tenantId
     * @param int $propertyId
     * @param string $action
     * @param array $propertyData
     * @return bool
     */
    public function publishPropertyUpdate(int $tenantId, int $propertyId, string $action, array $propertyData): bool
    {
        return $this->publishToTenant($tenantId, 'property_update', [
            'action' => $action,
            'property_id' => $propertyId,
            'property' => $propertyData
        ]);
    }

    /**
     * Obter usuários online
     *
     * @param int $tenantId
     * @return array
     */
    public function getOnlineUsers(int $tenantId): array
    {
        try {
            $key = "online:tenant:{$tenantId}";
            $users = Redis::smembers($key);

            return array_map('intval', $users);

        } catch (\Exception $e) {
            Log::error('Error getting online users', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Marcar usuário como online
     *
     * @param int $userId
     * @param int $tenantId
     * @param int $ttl Tempo em segundos
     * @return bool
     */
    public function setUserOnline(int $userId, int $tenantId, int $ttl = 300): bool
    {
        try {
            $key = "online:tenant:{$tenantId}";
            $userKey = "online:user:{$userId}";

            // Adicionar ao conjunto de usuários online do tenant
            Redis::sadd($key, $userId);
            Redis::expire($key, $ttl);

            // Marcar usuário como online com TTL
            Redis::setex($userKey, $ttl, time());

            return true;

        } catch (\Exception $e) {
            Log::error('Error setting user online', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Marcar usuário como offline
     *
     * @param int $userId
     * @param int $tenantId
     * @return bool
     */
    public function setUserOffline(int $userId, int $tenantId): bool
    {
        try {
            $key = "online:tenant:{$tenantId}";
            $userKey = "online:user:{$userId}";

            Redis::srem($key, $userId);
            Redis::del($userKey);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar se usuário está online
     *
     * @param int $userId
     * @return bool
     */
    public function isUserOnline(int $userId): bool
    {
        try {
            $userKey = "online:user:{$userId}";
            return Redis::exists($userKey) > 0;

        } catch (\Exception $e) {
            return false;
        }
    }
}
