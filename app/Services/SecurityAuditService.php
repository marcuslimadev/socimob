<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Serviço de Auditoria de Segurança
 */
class SecurityAuditService
{
    /**
     * Registrar evento de segurança
     *
     * @param string $event
     * @param array $data
     * @param string $severity (low, medium, high, critical)
     * @return void
     */
    public function logSecurityEvent(string $event, array $data = [], string $severity = 'medium'): void
    {
        try {
            // Log estruturado
            Log::channel('security')->log($severity, $event, array_merge($data, [
                'timestamp' => Carbon::now()->toIso8601String(),
                'severity' => $severity,
            ]));

            // Salvar no banco de dados se for evento crítico
            if (in_array($severity, ['high', 'critical'])) {
                DB::table('security_audit_logs')->insert([
                    'event' => $event,
                    'severity' => $severity,
                    'data' => json_encode($data),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'user_id' => Auth::id(),
                    'tenant_id' => app('tenant')?->id,
                    'created_at' => Carbon::now(),
                ]);
            }

            // Notificar admin se for crítico
            if ($severity === 'critical') {
                $this->notifySecurityTeam($event, $data);
            }

        } catch (\Exception $e) {
            Log::error('Failed to log security event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Registrar tentativa de login
     *
     * @param string $email
     * @param bool $success
     * @param string|null $failureReason
     * @return void
     */
    public function logLoginAttempt(string $email, bool $success, ?string $failureReason = null): void
    {
        $data = [
            'email' => $email,
            'success' => $success,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if (!$success) {
            $data['failure_reason'] = $failureReason;
        }

        $event = $success ? 'login_success' : 'login_failure';
        $severity = $success ? 'low' : 'medium';

        // Verificar tentativas falhadas recentes
        if (!$success) {
            $recentFailures = $this->getRecentLoginFailures($email, 15);
            if ($recentFailures >= 5) {
                $severity = 'high';
                $data['alert'] = 'Multiple failed login attempts detected';
            }
        }

        $this->logSecurityEvent($event, $data, $severity);
    }

    /**
     * Obter tentativas de login falhadas recentes
     *
     * @param string $email
     * @param int $minutes
     * @return int
     */
    private function getRecentLoginFailures(string $email, int $minutes = 15): int
    {
        try {
            return DB::table('security_audit_logs')
                ->where('event', 'login_failure')
                ->where('data->email', $email)
                ->where('created_at', '>=', Carbon::now()->subMinutes($minutes))
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Registrar alteração de dados sensíveis
     *
     * @param string $model
     * @param int $modelId
     * @param array $changes
     * @return void
     */
    public function logDataChange(string $model, int $modelId, array $changes): void
    {
        $this->logSecurityEvent('data_change', [
            'model' => $model,
            'model_id' => $modelId,
            'changes' => $changes,
            'user_id' => Auth::id(),
        ], 'low');
    }

    /**
     * Registrar acesso a dados sensíveis
     *
     * @param string $resource
     * @param int $resourceId
     * @param string $action
     * @return void
     */
    public function logSensitiveAccess(string $resource, int $resourceId, string $action): void
    {
        $this->logSecurityEvent('sensitive_access', [
            'resource' => $resource,
            'resource_id' => $resourceId,
            'action' => $action,
            'user_id' => Auth::id(),
        ], 'medium');
    }

    /**
     * Obter relatório de eventos de segurança
     *
     * @param array $filters
     * @return array
     */
    public function getSecurityReport(array $filters = []): array
    {
        try {
            $query = DB::table('security_audit_logs')
                ->orderBy('created_at', 'desc');

            // Aplicar filtros
            if (!empty($filters['severity'])) {
                $query->where('severity', $filters['severity']);
            }

            if (!empty($filters['event'])) {
                $query->where('event', $filters['event']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }

            if (!empty($filters['tenant_id'])) {
                $query->where('tenant_id', $filters['tenant_id']);
            }

            $events = $query->limit($filters['limit'] ?? 100)->get();

            // Estatísticas
            $stats = [
                'total_events' => $events->count(),
                'critical_events' => $events->where('severity', 'critical')->count(),
                'high_events' => $events->where('severity', 'high')->count(),
                'medium_events' => $events->where('severity', 'medium')->count(),
                'low_events' => $events->where('severity', 'low')->count(),
            ];

            // Agrupar por tipo de evento
            $byType = $events->groupBy('event')->map(function($group) {
                return $group->count();
            })->toArray();

            // IPs mais ativos
            $topIPs = $events->groupBy('ip_address')
                ->map(function($group) {
                    return $group->count();
                })
                ->sortDesc()
                ->take(10)
                ->toArray();

            return [
                'success' => true,
                'events' => $events->toArray(),
                'stats' => $stats,
                'by_type' => $byType,
                'top_ips' => $topIPs,
            ];

        } catch (\Exception $e) {
            Log::error('Error generating security report', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error generating security report',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Notificar equipe de segurança sobre evento crítico
     *
     * @param string $event
     * @param array $data
     * @return void
     */
    private function notifySecurityTeam(string $event, array $data): void
    {
        try {
            $tenantId = app('tenant')?->id;
            if (!$tenantId) {
                return; // Cannot notify without a tenant context
            }

            // Buscar admins
            $admins = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->get();

            foreach ($admins as $admin) {
                // Criar notificação
                DB::table('notifications')->insert([
                    'tenant_id' => $tenantId,
                    'user_id' => $admin->id,
                    'type' => 'security_alert',
                    'title' => 'Alerta de Segurança Crítico',
                    'message' => "Evento crítico detectado: {$event}",
                    'action_url' => '/notifications',
                    'channel' => 'in_app',
                    'data' => json_encode(array_merge($data, ['event' => $event])),
                    'is_read' => false,
                    'is_sent' => true,
                    'sent_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            Log::info('Security team notified', [
                'event' => $event,
                'admins_notified' => $admins->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to notify security team', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
        }
    }

    /**
     * Limpar logs antigos
     *
     * @param int $days
     * @return int Número de registros removidos
     */
    public function cleanOldLogs(int $days = 90): int
    {
        try {
            $deleted = DB::table('security_audit_logs')
                ->where('created_at', '<', Carbon::now()->subDays($days))
                ->where('severity', '!=', 'critical') // Manter logs críticos
                ->delete();

            Log::info('Old security logs cleaned', [
                'deleted' => $deleted,
                'older_than_days' => $days
            ]);

            return $deleted;

        } catch (\Exception $e) {
            Log::error('Error cleaning old logs', [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Verificar IPs suspeitos (múltiplas tentativas falhadas)
     *
     * @param int $minutes
     * @param int $threshold
     * @return array
     */
    public function getSuspiciousIPs(int $minutes = 60, int $threshold = 10): array
    {
        try {
            $suspicious = DB::table('security_audit_logs')
                ->select('ip_address', DB::raw('COUNT(*) as attempts'))
                ->where('event', 'login_failure')
                ->where('created_at', '>=', Carbon::now()->subMinutes($minutes))
                ->groupBy('ip_address')
                ->having('attempts', '>=', $threshold)
                ->orderBy('attempts', 'desc')
                ->get();

            return [
                'success' => true,
                'suspicious_ips' => $suspicious->toArray(),
                'count' => $suspicious->count(),
            ];

        } catch (\Exception $e) {
            Log::error('Error getting suspicious IPs', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
