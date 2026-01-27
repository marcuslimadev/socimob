<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Serviço de Cache
 * Gerencia cache de imóveis, leads e dados frequentemente acessados
 */
class CacheService
{
    /**
     * TTL padrão para cache (em segundos)
     */
    private $defaultTTL = 3600; // 1 hora

    /**
     * TTL por tipo de cache
     */
    private $cacheTTL = [
        'properties' => 1800, // 30 minutos
        'tenant_config' => 3600, // 1 hora
        'user' => 900, // 15 minutos
        'leads' => 600, // 10 minutos
        'statistics' => 300, // 5 minutos
    ];

    /**
     * Cachear imóveis de um tenant
     *
     * @param int $tenantId
     * @param array $filters
     * @return array
     */
    public function cacheProperties(int $tenantId, array $filters = []): array
    {
        $cacheKey = $this->buildCacheKey('properties', $tenantId, $filters);

        return Cache::remember($cacheKey, $this->cacheTTL['properties'], function() use ($tenantId, $filters) {
            Log::info('Cache miss - loading properties from database', [
                'tenant_id' => $tenantId,
                'filters' => array_keys($filters)
            ]);

            $query = DB::table('imo_properties')
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->where('exibir_imovel', true);

            // Aplicar filtros
            if (!empty($filters['tipo'])) {
                $query->where('tipo_imovel', $filters['tipo']);
            }

            if (!empty($filters['finalidade'])) {
                $query->where('finalidade_imovel', $filters['finalidade']);
            }

            if (!empty($filters['cidade'])) {
                $query->where('cidade', $filters['cidade']);
            }

            $properties = $query->orderBy('created_at', 'desc')
                ->limit($filters['limit'] ?? 100)
                ->get()
                ->toArray();

            return $properties;
        });
    }

    /**
     * Cachear configurações de um tenant
     *
     * @param int $tenantId
     * @return array|null
     */
    public function cacheTenantConfig(int $tenantId): ?array
    {
        $cacheKey = "tenant_config:{$tenantId}";

        return Cache::remember($cacheKey, $this->cacheTTL['tenant_config'], function() use ($tenantId) {
            Log::info('Cache miss - loading tenant config from database', [
                'tenant_id' => $tenantId
            ]);

            $tenant = DB::table('tenants')->where('id', $tenantId)->first();

            if (!$tenant) {
                return null;
            }

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'primary_color' => $tenant->primary_color,
                'secondary_color' => $tenant->secondary_color,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
            ];
        });
    }

    /**
     * Cachear dados de um usuário
     *
     * @param int $userId
     * @return array|null
     */
    public function cacheUser(int $userId): ?array
    {
        $cacheKey = "user:{$userId}";

        return Cache::remember($cacheKey, $this->cacheTTL['user'], function() use ($userId) {
            $user = DB::table('users')->where('id', $userId)->first();

            if (!$user) {
                return null;
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'is_active' => $user->is_active,
            ];
        });
    }

    /**
     * Cachear estatísticas do dashboard
     *
     * @param int $tenantId
     * @return array
     */
    public function cacheStatistics(int $tenantId): array
    {
        $cacheKey = "statistics:{$tenantId}";

        return Cache::remember($cacheKey, $this->cacheTTL['statistics'], function() use ($tenantId) {
            Log::info('Cache miss - calculating statistics', ['tenant_id' => $tenantId]);

            $stats = [];

            // Total de leads
            $stats['total_leads'] = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->count();

            // Leads novos (últimos 7 dias)
            $stats['new_leads_week'] = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            // Total de imóveis
            $stats['total_properties'] = DB::table('imo_properties')
                ->where('tenant_id', $tenantId)
                ->where('active', true)
                ->count();

            // Conversas ativas
            $stats['active_conversations'] = DB::table('conversas')
                ->where('tenant_id', $tenantId)
                ->where('status', 'ativa')
                ->count();

            // Taxa de conversão (exemplo simplificado)
            $totalLeads = $stats['total_leads'];
            $convertedLeads = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('status', 'convertido')
                ->count();

            $stats['conversion_rate'] = $totalLeads > 0
                ? round(($convertedLeads / $totalLeads) * 100, 2)
                : 0;

            return $stats;
        });
    }

    /**
     * Invalidar cache de imóveis de um tenant
     *
     * @param int $tenantId
     * @return void
     */
    public function invalidatePropertiesCache(int $tenantId): void
    {
        // Buscar todas as chaves de cache de properties deste tenant
        $pattern = "properties:{$tenantId}:*";

        try {
            // Limpar cache com padrão (depende do driver de cache)
            Cache::tags(["properties_{$tenantId}"])->flush();

            Log::info('Properties cache invalidated', ['tenant_id' => $tenantId]);

        } catch (\Exception $e) {
            // Se tags não são suportadas, limpar manualmente
            Log::warning('Cache tags not supported, manual invalidation needed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Invalidar cache de configuração de tenant
     *
     * @param int $tenantId
     * @return void
     */
    public function invalidateTenantConfigCache(int $tenantId): void
    {
        $cacheKey = "tenant_config:{$tenantId}";
        Cache::forget($cacheKey);

        Log::info('Tenant config cache invalidated', ['tenant_id' => $tenantId]);
    }

    /**
     * Invalidar cache de usuário
     *
     * @param int $userId
     * @return void
     */
    public function invalidateUserCache(int $userId): void
    {
        $cacheKey = "user:{$userId}";
        Cache::forget($cacheKey);

        Log::info('User cache invalidated', ['user_id' => $userId]);
    }

    /**
     * Invalidar cache de estatísticas
     *
     * @param int $tenantId
     * @return void
     */
    public function invalidateStatisticsCache(int $tenantId): void
    {
        $cacheKey = "statistics:{$tenantId}";
        Cache::forget($cacheKey);

        Log::info('Statistics cache invalidated', ['tenant_id' => $tenantId]);
    }

    /**
     * Limpar todo o cache de um tenant
     *
     * @param int $tenantId
     * @return void
     */
    public function clearTenantCache(int $tenantId): void
    {
        $this->invalidatePropertiesCache($tenantId);
        $this->invalidateTenantConfigCache($tenantId);
        $this->invalidateStatisticsCache($tenantId);

        Log::info('All tenant cache cleared', ['tenant_id' => $tenantId]);
    }

    /**
     * Construir chave de cache
     *
     * @param string $type
     * @param int $tenantId
     * @param array $params
     * @return string
     */
    private function buildCacheKey(string $type, int $tenantId, array $params = []): string
    {
        $key = "{$type}:{$tenantId}";

        if (!empty($params)) {
            ksort($params);
            $key .= ':' . md5(json_encode($params));
        }

        return $key;
    }

    /**
     * Obter estatísticas de uso do cache
     *
     * @return array
     */
    public function getCacheStats(): array
    {
        try {
            // Isso depende do driver de cache usado
            // Exemplo genérico
            return [
                'success' => true,
                'message' => 'Cache statistics (implementation depends on cache driver)',
                'driver' => config('cache.default', 'file'),
            ];

        } catch (\Exception $e) {
            Log::error('Error getting cache stats', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Pré-aquecer cache para melhor performance
     *
     * @param int $tenantId
     * @return array
     */
    public function warmupCache(int $tenantId): array
    {
        $warmed = [];

        try {
            // Cachear configurações
            $this->cacheTenantConfig($tenantId);
            $warmed[] = 'tenant_config';

            // Cachear imóveis principais
            $this->cacheProperties($tenantId, ['limit' => 50]);
            $warmed[] = 'properties';

            // Cachear estatísticas
            $this->cacheStatistics($tenantId);
            $warmed[] = 'statistics';

            Log::info('Cache warmed up', [
                'tenant_id' => $tenantId,
                'cached_types' => $warmed
            ]);

            return [
                'success' => true,
                'warmed' => $warmed
            ];

        } catch (\Exception $e) {
            Log::error('Error warming up cache', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'warmed' => $warmed
            ];
        }
    }
}
