<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        DB::table('notifications')
            ->select(['id', 'type', 'action_url', 'property_id', 'intention_id', 'data'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $data = $this->decodeData($row->data ?? null);
                    $normalized = $this->normalizeActionUrl(
                        $row->action_url ?? null,
                        $row->type ?? null,
                        $row->property_id ? (int) $row->property_id : null,
                        $row->intention_id ? (int) $row->intention_id : null,
                        $data,
                    );

                    if ($normalized === ($row->action_url ?? null)) {
                        continue;
                    }

                    DB::table('notifications')
                        ->where('id', $row->id)
                        ->update([
                            'action_url' => $normalized,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // No-op: this migration only normalizes legacy action_url values.
    }

    private function decodeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (is_object($data)) {
            return (array) $data;
        }

        if (is_string($data) && trim($data) !== '') {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function normalizeActionUrl(?string $actionUrl, ?string $type, ?int $propertyId, ?int $intentionId, array $data): ?string
    {
        $normalized = is_string($actionUrl) ? trim($actionUrl) : '';

        if ($normalized === '') {
            return $this->inferActionUrl($type, $propertyId, $intentionId, $data);
        }

        if (preg_match('#^/property/(\\d+)$#', $normalized, $matches)) {
            return '/portal/imovel/' . $matches[1];
        }

        if (preg_match('#^/properties/(\\d+)$#', $normalized, $matches)) {
            return '/properties/' . $matches[1] . '/editar';
        }

        if (preg_match('#^/assinaturas/(\\d+)$#', $normalized)) {
            return '/assinaturas';
        }

        return $normalized;
    }

    private function inferActionUrl(?string $type, ?int $propertyId, ?int $intentionId, array $data): ?string
    {
        $nestedActionUrl = $this->firstString($data, ['action_url']);
        if ($nestedActionUrl) {
            return $nestedActionUrl;
        }

        $leadId = $this->firstInt($data, ['lead_id', 'crm_lead_id']);
        $vistoriaId = $this->firstInt($data, ['vistoria_id']);
        $resolvedPropertyId = $propertyId ?: $this->firstInt($data, ['property_id', 'current_property_id', 'conflicting_property_id']);
        $registroTipo = $this->firstString($data, ['registro_tipo']);
        $notaId = $this->firstInt($data, ['nota_id', 'invoice_id']);

        if (in_array($type, ['new_lead', 'lead', 'lead_created'], true)) {
            return $leadId ? "/leads/{$leadId}" : '/leads';
        }

        if (in_array($type, ['message', 'nova_conversa'], true)) {
            return $leadId ? "/chat?leadId={$leadId}" : '/chat';
        }

        if (is_string($type) && str_starts_with($type, 'vistoria')) {
            return $vistoriaId ? "/vistorias/{$vistoriaId}" : '/vistorias';
        }

        if (is_string($type) && str_starts_with($type, 'assinatura')) {
            return '/assinaturas';
        }

        if ($type === 'property_match' && $resolvedPropertyId) {
            return "/portal/imovel/{$resolvedPropertyId}";
        }

        if (in_array($type, ['property_interest', 'property_new', 'price_change', 'status_change'], true) && $resolvedPropertyId) {
            return "/properties/{$resolvedPropertyId}/editar";
        }

        if ($registroTipo && $notaId) {
            return "/financeiro/notas/{$registroTipo}/{$notaId}";
        }

        if ($resolvedPropertyId) {
            return "/properties/{$resolvedPropertyId}/editar";
        }

        if ($intentionId) {
            return '/crm';
        }

        if (in_array($type, ['system_error', 'security_alert', 'system'], true)) {
            return '/notifications';
        }

        return null;
    }

    private function firstInt(array $data, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
};
