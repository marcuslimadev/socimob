<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Serviço de Otimização de Performance
 */
class PerformanceOptimizationService
{
    /**
     * Criar índices necessários para otimização
     *
     * @return array
     */
    public function createOptimizedIndexes(): array
    {
        $created = [];
        $errors = [];

        try {
            // Índices para tabela de leads
            if (!$this->indexExists('leads', 'idx_leads_tenant_status')) {
                DB::statement('CREATE INDEX idx_leads_tenant_status ON leads(tenant_id, status)');
                $created[] = 'leads.idx_leads_tenant_status';
            }

            if (!$this->indexExists('leads', 'idx_leads_created_at')) {
                DB::statement('CREATE INDEX idx_leads_created_at ON leads(created_at DESC)');
                $created[] = 'leads.idx_leads_created_at';
            }

            if (!$this->indexExists('leads', 'idx_leads_corretor')) {
                DB::statement('CREATE INDEX idx_leads_corretor ON leads(corretor_id, status)');
                $created[] = 'leads.idx_leads_corretor';
            }

            // Índices para tabela de imóveis
            if (!$this->indexExists('imo_properties', 'idx_properties_tenant_active')) {
                DB::statement('CREATE INDEX idx_properties_tenant_active ON imo_properties(tenant_id, active, exibir_imovel)');
                $created[] = 'imo_properties.idx_properties_tenant_active';
            }

            if (!$this->indexExists('imo_properties', 'idx_properties_cidade')) {
                DB::statement('CREATE INDEX idx_properties_cidade ON imo_properties(cidade, tipo_imovel)');
                $created[] = 'imo_properties.idx_properties_cidade';
            }

            if (!$this->indexExists('imo_properties', 'idx_properties_valor')) {
                DB::statement('CREATE INDEX idx_properties_valor ON imo_properties(valor_venda, finalidade_imovel)');
                $created[] = 'imo_properties.idx_properties_valor';
            }

            // Índices para tabela de conversas
            if (!$this->indexExists('conversas', 'idx_conversas_tenant_status')) {
                DB::statement('CREATE INDEX idx_conversas_tenant_status ON conversas(tenant_id, status)');
                $created[] = 'conversas.idx_conversas_tenant_status';
            }

            if (!$this->indexExists('conversas', 'idx_conversas_telefone')) {
                DB::statement('CREATE INDEX idx_conversas_telefone ON conversas(telefone, status)');
                $created[] = 'conversas.idx_conversas_telefone';
            }

            // Índices para tabela de mensagens
            if (!$this->indexExists('mensagens', 'idx_mensagens_conversa')) {
                DB::statement('CREATE INDEX idx_mensagens_conversa ON mensagens(conversa_id, sent_at DESC)');
                $created[] = 'mensagens.idx_mensagens_conversa';
            }

            if (!$this->indexExists('mensagens', 'idx_mensagens_direction')) {
                DB::statement('CREATE INDEX idx_mensagens_direction ON mensagens(conversa_id, direction)');
                $created[] = 'mensagens.idx_mensagens_direction';
            }

            // Índices para tabela de notificações
            if (!$this->indexExists('notifications', 'idx_notifications_user_read')) {
                DB::statement('CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read, created_at DESC)');
                $created[] = 'notifications.idx_notifications_user_read';
            }

            Log::info('Optimized indexes created', [
                'created' => $created,
                'count' => count($created)
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating indexes', [
                'error' => $e->getMessage(),
                'created_so_far' => $created
            ]);

            $errors[] = $e->getMessage();
        }

        return [
            'success' => empty($errors),
            'created' => $created,
            'errors' => $errors
        ];
    }

    /**
     * Verificar se índice existe
     *
     * @param string $table
     * @param string $indexName
     * @return bool
     */
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Analisar queries lentas
     *
     * @return array
     */
    public function analyzeSlowQueries(): array
    {
        try {
            // Habilitar log de queries lentas
            DB::enableQueryLog();

            // Análise básica de tabelas
            $tables = ['leads', 'imo_properties', 'conversas', 'mensagens', 'notifications'];
            $analysis = [];

            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $count = DB::table($table)->count();
                $size = DB::select("SELECT
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?", [$table]);

                $analysis[$table] = [
                    'row_count' => $count,
                    'size_mb' => $size[0]->size_mb ?? 0,
                ];
            }

            return [
                'success' => true,
                'tables' => $analysis
            ];

        } catch (\Exception $e) {
            Log::error('Error analyzing slow queries', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Otimizar tabelas do banco
     *
     * @return array
     */
    public function optimizeTables(): array
    {
        $optimized = [];
        $errors = [];

        $tables = ['leads', 'imo_properties', 'conversas', 'mensagens', 'users', 'notifications'];

        foreach ($tables as $table) {
            try {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                DB::statement("OPTIMIZE TABLE {$table}");
                $optimized[] = $table;

                Log::info('Table optimized', ['table' => $table]);

            } catch (\Exception $e) {
                $errors[$table] = $e->getMessage();
                Log::warning('Error optimizing table', [
                    'table' => $table,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => empty($errors),
            'optimized' => $optimized,
            'errors' => $errors
        ];
    }

    /**
     * Comprimir respostas JSON (middleware helper)
     *
     * @param array $data
     * @return string
     */
    public static function compressJSON(array $data): string
    {
        // Remove espaços em branco desnecessários
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Implementar lazy loading para imagens
     * Retorna URLs otimizadas com placeholder
     *
     * @param array $images
     * @return array
     */
    public function optimizeImageURLs(array $images): array
    {
        return array_map(function($image) {
            if (is_string($image)) {
                return [
                    'url' => $image,
                    'thumbnail' => $this->generateThumbnailURL($image),
                    'lazy' => true
                ];
            }

            if (is_array($image) && isset($image['url'])) {
                $image['thumbnail'] = $this->generateThumbnailURL($image['url']);
                $image['lazy'] = true;
                return $image;
            }

            return $image;
        }, $images);
    }

    /**
     * Gerar URL de thumbnail (placeholder para implementação futura)
     *
     * @param string $imageURL
     * @return string
     */
    private function generateThumbnailURL(string $imageURL): string
    {
        // TODO: Implementar geração de thumbnails real
        // Por enquanto, retorna a URL original
        return $imageURL;
    }

    /**
     * Limpar dados antigos para melhorar performance
     *
     * @param int $days
     * @return array
     */
    public function cleanupOldData(int $days = 180): array
    {
        $cleaned = [];

        try {
            // Limpar mensagens antigas de conversas encerradas
            $deletedMessages = DB::table('mensagens')
                ->whereIn('conversa_id', function($query) use ($days) {
                    $query->select('id')
                        ->from('conversas')
                        ->where('status', 'encerrada')
                        ->where('updated_at', '<', now()->subDays($days));
                })
                ->delete();

            $cleaned['old_messages'] = $deletedMessages;

            // Limpar notificações antigas lidas
            $deletedNotifications = DB::table('notifications')
                ->where('is_read', true)
                ->where('created_at', '<', now()->subDays($days))
                ->delete();

            $cleaned['old_notifications'] = $deletedNotifications;

            Log::info('Old data cleaned up', $cleaned);

            return [
                'success' => true,
                'cleaned' => $cleaned
            ];

        } catch (\Exception $e) {
            Log::error('Error cleaning up old data', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'cleaned' => $cleaned
            ];
        }
    }

    /**
     * Relatório de performance
     *
     * @return array
     */
    public function getPerformanceReport(): array
    {
        $report = [];

        try {
            // Tamanho do banco de dados
            $dbSize = DB::select("SELECT
                SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS size_mb,
                COUNT(*) AS table_count
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()");

            $report['database'] = [
                'size_mb' => round($dbSize[0]->size_mb ?? 0, 2),
                'table_count' => $dbSize[0]->table_count ?? 0
            ];

            // Estatísticas de cache
            $cacheService = new CacheService();
            $report['cache'] = $cacheService->getCacheStats();

            // Tabelas maiores
            $largestTables = DB::select("SELECT
                TABLE_NAME as name,
                ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb,
                TABLE_ROWS as rows
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                LIMIT 10");

            $report['largest_tables'] = $largestTables;

            // Índices
            $report['indexes'] = $this->getIndexReport();

            return [
                'success' => true,
                'report' => $report
            ];

        } catch (\Exception $e) {
            Log::error('Error generating performance report', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Relatório de índices
     *
     * @return array
     */
    private function getIndexReport(): array
    {
        try {
            $tables = ['leads', 'imo_properties', 'conversas', 'mensagens'];
            $indexes = [];

            foreach ($tables as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                $tableIndexes = DB::select("SHOW INDEX FROM {$table}");
                $indexes[$table] = count($tableIndexes);
            }

            return $indexes;

        } catch (\Exception $e) {
            return [];
        }
    }
}
