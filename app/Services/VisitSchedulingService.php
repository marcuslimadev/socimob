<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VisitSchedulingService
{
    public function schedule(array $payload): array
    {
        VisitasTablesManager::ensureVisitasTableExists();

        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $propertyId = isset($payload['property_id']) ? (int) $payload['property_id'] : null;
        $leadId = isset($payload['lead_id']) ? (int) $payload['lead_id'] : null;
        $assignedUserId = $this->resolveAssignedUserId(
            $tenantId,
            $propertyId,
            $leadId,
            isset($payload['assigned_user_id']) ? (int) $payload['assigned_user_id'] : null,
        );

        $propertyTitle = $payload['property_titulo'] ?? $this->resolvePropertyTitle($propertyId);
        $dateTime = $this->normalizeDateTime($payload['data_hora'] ?? null);
        $now = now();

        $visitId = DB::table('visitas')->insertGetId([
            'tenant_id' => $tenantId,
            'property_id' => $propertyId,
            'lead_id' => $leadId,
            'property_titulo' => $propertyTitle,
            'nome' => trim((string) ($payload['nome'] ?? '')),
            'email' => $this->nullableString($payload['email'] ?? null),
            'telefone' => $this->nullableString($payload['telefone'] ?? null),
            'data_hora' => $dateTime,
            'status' => $payload['status'] ?? 'pendente',
            'observacoes' => $this->nullableString($payload['observacoes'] ?? null),
            'assigned_user_id' => $assignedUserId,
            'created_by_user_id' => isset($payload['created_by_user_id']) ? (int) $payload['created_by_user_id'] : null,
            'origem' => $this->nullableString($payload['origem'] ?? 'manual') ?? 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $visit = DB::table('visitas')->where('id', $visitId)->first();

        if ($assignedUserId) {
            $this->notifyAssignedUser($tenantId, $assignedUserId, $visit);
        }

        return [
            'id' => $visitId,
            'assigned_user_id' => $assignedUserId,
            'data_hora' => $dateTime,
        ];
    }

    public function normalizeDateTime(mixed $value): string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            throw new \InvalidArgumentException('Data/hora da visita é obrigatória.');
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})$/', $raw, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', (int) $matches[3], (int) $matches[2], (int) $matches[1], (int) $matches[4], (int) $matches[5]);
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            throw new \InvalidArgumentException('Formato de data/hora inválido para visita.');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function resolveAssignedUserId(int $tenantId, ?int $propertyId, ?int $leadId, ?int $requestedAssignedUserId): ?int
    {
        if ($requestedAssignedUserId) {
            $requestedUser = User::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('role', ['corretor', 'admin', 'super_admin'])
                ->where('is_active', true)
                ->find($requestedAssignedUserId);

            if ($requestedUser) {
                return (int) $requestedUser->id;
            }
        }

        if ($leadId) {
            $lead = Lead::query()->withoutTenant()->find($leadId);
            if ($lead?->corretor_id) {
                return (int) $lead->corretor_id;
            }
        }

        if ($propertyId) {
            $property = DB::table('properties')
                ->where('tenant_id', $tenantId)
                ->where('id', $propertyId)
                ->select('corretor_id', 'user_id')
                ->first();

            if (!empty($property?->corretor_id)) {
                return (int) $property->corretor_id;
            }

            if (!empty($property?->user_id)) {
                return (int) $property->user_id;
            }
        }

        $fallbackUser = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['corretor', 'admin'])
            ->where('is_active', true)
            ->orderByRaw("case when role = 'corretor' then 0 else 1 end")
            ->orderBy('id')
            ->first();

        return $fallbackUser ? (int) $fallbackUser->id : null;
    }

    private function resolvePropertyTitle(?int $propertyId): ?string
    {
        if (!$propertyId) {
            return null;
        }

        return DB::table('properties')->where('id', $propertyId)->value('titulo');
    }

    private function notifyAssignedUser(int $tenantId, int $assignedUserId, object $visit): void
    {
        Notification::create([
            'tenant_id' => $tenantId,
            'user_id' => $assignedUserId,
            'property_id' => $visit->property_id ?? null,
            'type' => 'visita_agendada',
            'title' => 'Nova visita agendada',
            'message' => sprintf('Visita agendada para %s em %s.', $visit->nome, $visit->property_titulo ?: 'imóvel não informado'),
            'action_url' => '/agenda',
            'data' => [
                'visita_id' => $visit->id,
                'lead_id' => $visit->lead_id ?? null,
                'assigned_user_id' => $assignedUserId,
            ],
            'channel' => 'in_app',
            'is_read' => false,
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}