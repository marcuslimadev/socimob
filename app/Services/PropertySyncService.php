<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de sincronização de imóveis
 * Busca dados da API da Exclusiva Lar e atualiza o banco de dados
 */
class PropertySyncService
{
    private $apiToken;
    private $baseUrl = 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel';
    private $geocodeCache = [];
    private $lastGeocodeCall = 0;
    private PropertyTrashService $trashService;
    
    public function __construct()
    {
        $this->apiToken = env('EXCLUSIVA_API_TOKEN');
        $this->trashService = app(PropertyTrashService::class);
        
        if (!$this->apiToken) {
            // throw new \Exception('EXCLUSIVA_API_TOKEN não configurado no .env');
        }
    }
    
    /**
     * Sincronizar todos os imóveis
     */
    public function syncAll()
    {
        $startTime = microtime(true);
        
        Log::info('🏠 Iniciando sincronização de imóveis...');
        
        try {
            $tenant = $this->resolveSyncTenant();
            $preferImobiBrasil = $tenant && ImobiBrasilService::isEnabled($tenant);

            $stats = [
                'found' => 0,
                'new' => 0,
                'updated' => 0,
                'errors' => 0,
                'restored' => 0,
            ];

            $errorDetails = [];
            $sourceCodes = [];
            
            $page = 1;
            $totalPages = 1;
            
            $perPage = 50;
            $pageSignatures = [];

            if ($preferImobiBrasil) {
                Log::info('Usando Imobi Brasil como fonte principal da sincronização', [
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Loop por todas as páginas
            do {
                Log::info("📄 Buscando página {$page}...");

                if ($preferImobiBrasil) {
                    $lista = ImobiBrasilService::listProperties($tenant, [
                        'page' => $page,
                        'limit' => $perPage,
                    ]);

                    if (!($lista['success'] ?? false) || !isset($lista['result_set']['data'])) {
                        throw new \Exception('Resposta da Imobi Brasil inválida: estrutura esperada não encontrada');
                    }

                    $resultSet = $lista['result_set'];
                } else {
                    // Montar query string para paginação
                    $queryString = http_build_query([
                        'pagina' => $page,
                        'limite' => $perPage
                    ]);
                    
                    // Buscar lista de imóveis (com paginação) - tentando GET primeiro
                    try {
                        $lista = $this->callApi("/lista?{$queryString}");
                    } catch (\Exception $e) {
                        // Se falhar, tentar POST
                        Log::info("GET /lista falhou, tentando POST...");
                        $lista = $this->callApiPost("/lista", [
                            'pagina' => $page,
                            'limite' => $perPage
                        ]);
                    }
                    
                    if (!isset($lista['resultSet']['data'])) {
                        throw new \Exception('Resposta da API inválida: estrutura esperada não encontrada');
                    }

                    $resultSet = $lista['resultSet'];
                }

                $imoveis = $resultSet['data'];
                $totalPages = max(1, (int) ($resultSet['total_pages'] ?? 1));
                $totalItems = (int) ($resultSet['total_items'] ?? count($imoveis));
                $apiPage = (int) ($resultSet['page'] ?? $page);

                $pageCodes = array_values(array_filter(array_map(function ($item) {
                    $codigo = $item['codigoImovel'] ?? null;
                    return $codigo === null || $codigo === '' ? null : (string) $codigo;
                }, $imoveis)));

                if (!empty($pageCodes)) {
                    $signature = md5(json_encode($pageCodes));
                    if (isset($pageSignatures[$signature])) {
                        Log::warning('Página repetida detectada durante sincronização de imóveis', [
                            'tenant_id' => $tenant?->id,
                            'requested_page' => $page,
                            'api_page' => $apiPage,
                            'first_seen_page' => $pageSignatures[$signature],
                            'source' => $preferImobiBrasil ? 'imobi_brasil' : 'api_externa',
                        ]);
                        break;
                    }

                    $pageSignatures[$signature] = $page;
                }
                
                Log::info("📊 Página {$page}/{$totalPages} - " . count($imoveis) . " imóveis", [
                    'total_items' => $totalItems,
                    'per_page' => $resultSet['per_page'] ?? 50,
                    'api_page' => $apiPage,
                ]);
                
                $stats['found'] += count($imoveis);
                
                foreach ($imoveis as $item) {
                    $codigo = $item['codigoImovel'] ?? null;
                    
                    if (!$codigo) {
                        $stats['errors']++;
                        continue;
                    }

                    $sourceCodes[] = (string) $codigo;

                    try {
                        if ($preferImobiBrasil) {
                            $response = ImobiBrasilService::getPropertyData((int) $codigo, $tenant);

                            if (!($response['success'] ?? false) || !isset($response['result_set'])) {
                                throw new \Exception("Dados não encontrados na Imobi Brasil para imóvel {$codigo}");
                            }

                            $imovel = $response['result_set'];
                            $data = $this->mapPropertyData($imovel, true);
                        } else {
                            // Buscar dados completos do imóvel (GET ainda funciona)
                            $response = $this->callApi("/dados/{$codigo}");
                            
                            if (!isset($response['resultSet'])) {
                                throw new \Exception("Dados não encontrados para imóvel {$codigo}");
                            }
                            
                            $imovel = $response['resultSet'];
                            $data = $this->mapPropertyData($imovel);
                        }
                        
                        // Contar imagens para logging
                        $numImagens = 0;
                        if (isset($data['imagens']) && is_array($data['imagens'])) {
                            $numImagens = count($data['imagens']);
                        }
                        
                        $persisted = $this->persistPropertyData((string) $codigo, $data);

                        if ($persisted['restored']) {
                            $stats['restored']++;
                        }

                        if ($persisted['action'] === 'created') {
                            $stats['new']++;
                            Log::debug("➕ Imóvel {$codigo} criado ({$numImagens} imagens)");
                        } else {
                            $stats['updated']++;
                            Log::debug("✏️ Imóvel {$codigo} atualizado ({$numImagens} imagens)", [
                                'active' => $data['active'] ?? null,
                                'exibir_imovel' => $data['exibir_imovel'] ?? null,
                            ]);
                        }
                        
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $errorMessage = $e->getMessage();
                        $errorDetails[] = [
                            'codigo' => $codigo,
                            'message' => $errorMessage,
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];
                        Log::error("❌ Erro ao processar imóvel {$codigo}", [
                            'error' => $errorMessage
                        ]);
                    }
                }
                
                $page++;
                
            } while ($page <= $totalPages);

            if ($preferImobiBrasil) {
                $availableCodes = array_values(array_unique(array_filter(array_map('strval', $sourceCodes))));
                $imobiBackfill = [
                    'success' => true,
                    'available' => count($availableCodes),
                    'available_codes' => $availableCodes,
                    'imported' => 0,
                    'restored' => 0,
                    'errors' => 0,
                ];
            } else {
                $imobiBackfill = $this->importMissingImobiProperties($tenant);
            }

            $stats['imobi_available'] = $imobiBackfill['available'] ?? 0;
            $stats['imobi_imported'] = $imobiBackfill['imported'] ?? 0;
            $stats['imobi_restored'] = $imobiBackfill['restored'] ?? 0;
            $stats['imobi_errors'] = $imobiBackfill['errors'] ?? 0;

            $knownCodes = array_values(array_unique(array_filter(array_map(
                'strval',
                array_merge($sourceCodes, $imobiBackfill['available_codes'] ?? [])
            ))));

            $authoritativeCodes = $knownCodes;
            $cleanupReason = 'missing_from_source';
            if (($imobiBackfill['success'] ?? false) && !empty($imobiBackfill['available_codes'])) {
                $authoritativeCodes = $imobiBackfill['available_codes'];
                $cleanupReason = 'missing_from_imobi_brasil';
            }

            $stats['trashed'] = $this->trashMissingImportedProperties($authoritativeCodes, $cleanupReason);
            
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            
            // Contar quantos imóveis têm imagens
            $comImagens = Property::whereNotNull('imagens')
                ->where('imagens', '!=', '[]')
                ->where('imagens', '!=', '')
                ->count();
            
            $stats['with_images'] = $comImagens;
            
            Log::info('✅ Sincronização concluída', [
                'stats' => $stats,
                'time_ms' => $elapsed
            ]);
            
            return [
                'success' => true,
                'stats' => $stats,
                'time_ms' => $elapsed,
                'errors_detail' => $errorDetails
            ];
            
        } catch (\Exception $e) {
            Log::error('❌ Erro na sincronização de imóveis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function trashImportedPropertyByCode(string $codigo, string $reason): void
    {
        $property = Property::withTrashed()
            ->where('codigo', $codigo)
            ->whereColumn('external_id', 'codigo')
            ->first();

        if ($property && !$property->trashed()) {
            $this->trashService->moveToTrash($property, 'sync', $reason, null, ['codigo' => $codigo]);
        }
    }

    private function trashMissingImportedProperties(array $sourceCodes, string $reason = 'missing_from_source'): int
    {
        $normalizedCodes = array_values(array_unique(array_filter(array_map('strval', $sourceCodes))));
        if (empty($normalizedCodes)) {
            return 0;
        }

        $query = Property::query()->where(function ($builder) {
            $builder->where(function ($subQuery) {
                $subQuery->whereNotNull('external_id')
                    ->whereColumn('external_id', 'codigo');
            })->orWhereNotNull('imobi_brasil_external_id');
        });

        if (app()->bound('tenant')) {
            $query->where('tenant_id', app('tenant')->id);
        }

        $properties = $query->get();

        $trashed = 0;
        foreach ($properties as $property) {
            $comparisonCode = $this->resolveAuthorityCode($property);
            if ($comparisonCode && in_array($comparisonCode, $normalizedCodes, true)) {
                continue;
            }

            $this->trashService->moveToTrash(
                $property,
                'sync',
                $reason,
                null,
                [
                    'codigo' => $property->codigo,
                    'authority_code' => $comparisonCode,
                ]
            );
            $trashed++;
        }

        return $trashed;
    }
    
    /**
     * Mapear dados do imóvel da API para o formato do banco
     */
    private function mapPropertyData($imovel, bool $fromImobiBrasil = false)
    {
r        // Converter áreas - nova estrutura da API
        $areaPrivativa = isset($imovel['area']['privativa']['valor']) ? 
            $this->parseArea($imovel['area']['privativa']['valor']) : null;
        $areaTotal = isset($imovel['area']['total']['valor']) ? 
            $this->parseArea($imovel['area']['total']['valor']) : null;
        $areaTerreno = isset($imovel['area']['terreno']['valor']) ? 
            $this->parseArea($imovel['area']['terreno']['valor']) : null;
        
        // Características - agora pode ser array vazio
        $caracteristicas = [];
        if (!empty($imovel['caracteristicas']) && is_array($imovel['caracteristicas'])) {
            foreach ($imovel['caracteristicas'] as $carac) {
                if (is_string($carac)) {
                    $caracteristicas[] = $carac;
                } elseif (isset($carac['nomeCaracteristica'])) {
                    $caracteristicas[] = $carac['nomeCaracteristica'];
                }
            }
        }
        
        $imagensData = $this->resolvePropertyImageUrls($imovel);
        $imagemDestaque = $imagensData[0] ?? null;

        $latitude = $imovel['endereco']['latitude'] ?? null;
        $longitude = $imovel['endereco']['longitude'] ?? null;

        if (empty($latitude) || empty($longitude)) {
            [$latitude, $longitude] = $this->resolveCoordinates($imovel);
        }

        $latitude = ($latitude === null || $latitude === '') ? null : (float) $latitude;
        $longitude = ($longitude === null || $longitude === '') ? null : (float) $longitude;
        
        // Montar título baseado no tipo e localização
        $tipo = $imovel['descricaoTipoImovel'] ?? 'Imóvel';
        $bairro = $imovel['endereco']['bairro'] ?? '';
        $cidade = $imovel['endereco']['cidade'] ?? '';
        $titulo = "$tipo no $bairro - $cidade";
        
        // Montar endereço completo
        $endereco = $imovel['endereco'] ?? [];
        $enderecoCompleto = implode(', ', array_filter([
            $endereco['logradouro'] ?? '',
            $endereco['numero'] ?? '',
            $endereco['complemento'] ?? '',
            $endereco['bairro'] ?? '',
            $endereco['cidade'] ?? '',
            $endereco['estado'] ?? ''
        ])) ?: 'Endereço não informado';
        
        $data = [
            'codigo' => $imovel['codigoImovel'],
            'external_id' => strval($imovel['codigoImovel']),
            'codigo_imovel' => $referenceCode !== '' ? $referenceCode : null,
            'referencia_imovel' => $referenceCode !== '' ? $referenceCode : null,
            'titulo' => $titulo,
            'finalidade_imovel' => $this->mapearFinalidade($imovel['finalidadeImovel'] ?? 'Venda'),
            'tipo_imovel' => $this->mapearTipo($imovel['descricaoTipoImovel'] ?? 'Casa'),
            'descricao' => $this->formatDescriptionWithAI($imovel['descricaoImovel'] ?? null),
            'dormitorios' => intval($imovel['dormitorios'] ?? 0),
            'banheiros' => intval($imovel['banheiros'] ?? 0),
            'garagem' => intval($imovel['garagem'] ?? 0),
            'valor_venda' => floatval($imovel['valorEsperado'] ?? 0),
            'logradouro' => $enderecoCompleto,
            'cidade' => $imovel['endereco']['cidade'] ?? null,
            'estado' => $imovel['endereco']['estado'] ?? null,
            'bairro' => $imovel['endereco']['bairro'] ?? null,
            'numero' => $imovel['endereco']['numero'] ?? null,
            'complemento' => $imovel['endereco']['complemento'] ?? null,
            'cep' => $imovel['endereco']['cep'] ?? null,
            'area_total' => $areaTotal,
            'area_privativa' => $areaPrivativa,
            'area_terreno' => $areaTerreno,
            'imagens' => $imagensData, // Array será convertido automaticamente pelo cast
            'imagem_destaque' => $imagemDestaque,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'exibir_imovel' => !empty($imovel['exibirImovel']),
            'active' => !empty($imovel['exibirImovel']) && (int) ($imovel['statusImovel'] ?? 1) === 1,
            'last_sync' => date('Y-m-d H:i:s')
        ];
        
        // Garantir que o tenant_id seja incluído explicitamente (segurança adicional)
        if (app()->bound('tenant')) {
            $data['tenant_id'] = app('tenant')->id;
        }

        if ($fromImobiBrasil) {
            $data['imobi_brasil_external_id'] = strval($imovel['codigoImovel']);
            $data['imobi_brasil_sent'] = true;
            $data['imobi_brasil_sent_at'] = now();
            $data['imobi_brasil_error'] = null;
        }
        
        return $data;
    }
    
    /**
     * Converter área de string para float
     */
    private function parseArea($valor)
    {
        if (!$valor) return null;
        return (float) str_replace(',', '.', $valor);
    }

    private function parseApiDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $normalized = str_replace('/', '-', trim((string) $value));
        $timestamp = strtotime($normalized);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    /**
     * Mapear tipo de imóvel para formato padronizado
     */
    private function mapearTipo($tipo)
    {
        $tipo = strtolower($tipo);
        
        // Casa e variações
        if (strpos($tipo, 'casa') !== false) return 'casa';
        
        // Apartamento e variações
        if (strpos($tipo, 'apartamento') !== false || 
            strpos($tipo, 'apto') !== false ||
            strpos($tipo, 'flat') !== false ||
            strpos($tipo, 'studio') !== false ||
            strpos($tipo, 'kitnet') !== false ||
            strpos($tipo, 'cobertura') !== false) {
            return 'apartamento';
        }
        
        // Terreno e variações
        if (strpos($tipo, 'terreno') !== false ||
            strpos($tipo, 'lote') !== false ||
            strpos($tipo, 'área') !== false) {
            return 'terreno';
        }
        
        // Comercial e variações
        if (strpos($tipo, 'comercial') !== false ||
            strpos($tipo, 'sala') !== false ||
            strpos($tipo, 'loja') !== false ||
            strpos($tipo, 'galpão') !== false ||
            strpos($tipo, 'galpao') !== false ||
            strpos($tipo, 'ponto comercial') !== false ||
            strpos($tipo, 'prédio') !== false ||
            strpos($tipo, 'predio') !== false) {
            return 'comercial';
        }
        
        // Padrão
        return 'casa';
    }

    /**
     * Mapear finalidade do imóvel
     */
    private function mapearFinalidade($finalidade)
    {
        $finalidade = strtolower((string) $finalidade);

        $hasVenda = strpos($finalidade, 'vend') !== false;
        $hasAluguel = strpos($finalidade, 'alug') !== false || strpos($finalidade, 'loca') !== false;
        $hasTemporada = strpos($finalidade, 'temporad') !== false;

        if ($hasVenda && $hasAluguel) {
            return 'venda_aluguel';
        }

        if ($hasTemporada && $hasAluguel) {
            return 'aluguel_temporada';
        }

        if ($hasTemporada) {
            return 'temporada';
        }

        if ($hasVenda) {
            return 'venda';
        }

        if ($hasAluguel) {
            return 'aluguel';
        }
        
        return 'venda';
    }

    private function persistPropertyData(string $codigo, array $data, ?string $authorityCode = null): array
    {
        $authorityCode = $authorityCode ?: ($data['imobi_brasil_external_id'] ?? null);
        $referenceCode = trim((string) ($data['referencia_imovel'] ?? $data['codigo_imovel'] ?? ''));
        $existing = $this->findExistingProperty($codigo, $authorityCode, $referenceCode !== '' ? $referenceCode : null);
        $restored = false;

        if ($existing) {
            if ($existing->trashed()) {
                $this->trashService->restoreFromTrash($existing);
                $existing = $existing->fresh();
                $restored = true;
            }

            $existing->update($data);

            return [
                'action' => 'updated',
                'restored' => $restored,
                'property_id' => $existing->id,
            ];
        }

        $property = Property::create($data);

        return [
            'action' => 'created',
            'restored' => false,
            'property_id' => $property->id,
        ];
    }

    private function findExistingProperty(string $codigo, ?string $authorityCode = null, ?string $referenceCode = null): ?Property
    {
        $query = Property::withTrashed()
            ->where(function ($builder) use ($codigo, $authorityCode, $referenceCode) {
                $builder->where('codigo', $codigo);

                if ($authorityCode) {
                    $builder->orWhere('imobi_brasil_external_id', $authorityCode)
                        ->orWhere('external_id', $authorityCode);
                }

                if ($referenceCode) {
                    $builder->orWhere('codigo_imovel', $referenceCode)
                        ->orWhere('referencia_imovel', $referenceCode);
                }
            });

        if (app()->bound('tenant')) {
            $tenantId = app('tenant')->id;
            $query->where('tenant_id', $tenantId);
        }

        if ($referenceCode) {
            $query->orderByRaw(
                "CASE WHEN codigo_imovel = ? OR referencia_imovel = ? THEN 0 ELSE 1 END",
                [$referenceCode, $referenceCode]
            );
        }

        return $query
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveAuthorityCode(Property $property): ?string
    {
        if (!empty($property->imobi_brasil_external_id)) {
            return (string) $property->imobi_brasil_external_id;
        }

        if (!empty($property->external_id)) {
            return (string) $property->external_id;
        }

        return $property->codigo ? (string) $property->codigo : null;
    }

    private function resolveSyncTenant(): ?Tenant
    {
        if (app()->bound('tenant')) {
            $tenant = app('tenant');
            if ($tenant instanceof Tenant) {
                return $tenant;
            }
        }

        return null;
    }

    private function fetchImobiBrasilCodes(Tenant $tenant): array
    {
        $codes = [];
        $page = 1;
        $totalPages = 1;
        $limit = 100;

        do {
            $response = $this->listImobiBrasilPage($tenant, $page, $limit);
            if (!($response['success'] ?? false)) {
                throw new \RuntimeException($response['error'] ?? 'Falha ao listar imóveis na Imobi Brasil.');
            }

            $resultSet = $response['result_set'] ?? [];
            $items = $resultSet['data'] ?? [];
            $totalPages = max(1, (int) ($resultSet['total_pages'] ?? 1));

            foreach ($items as $item) {
                $codigo = $item['codigoImovel'] ?? null;
                if ($codigo !== null && $codigo !== '') {
                    $codes[] = (string) $codigo;
                }
            }

            $page++;
        } while ($page <= $totalPages);

        return array_values(array_unique($codes));
    }

    private function listImobiBrasilPage(Tenant $tenant, int $page, int $limit): array
    {
        $attempts = [
            ['page' => $page, 'limit' => $limit],
            ['pagina' => $page, 'limite' => $limit],
        ];

        $lastError = 'Falha ao listar imóveis na Imobi Brasil.';

        foreach ($attempts as $params) {
            $response = ImobiBrasilService::listProperties($tenant, $params);
            if (($response['success'] ?? false) && isset($response['result_set'])) {
                return $response;
            }

            if (!empty($response['error'])) {
                $lastError = $response['error'];
            }
        }

        return ['success' => false, 'error' => $lastError];
    }

    public function importMissingImobiProperties(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?: $this->resolveSyncTenant();

        if (!$tenant) {
            return [
                'success' => false,
                'available' => 0,
                'available_codes' => [],
                'imported' => 0,
                'restored' => 0,
                'errors' => 0,
                'error' => 'Tenant não resolvido para complemento Imobi Brasil.',
            ];
        }

        if (!ImobiBrasilService::isEnabled($tenant)) {
            return [
                'success' => false,
                'available' => 0,
                'available_codes' => [],
                'imported' => 0,
                'restored' => 0,
                'errors' => 0,
                'error' => 'Integração Imobi Brasil não configurada para este tenant.',
            ];
        }

        try {
            $availableCodes = $this->fetchImobiBrasilCodes($tenant);

            $existing = Property::withTrashed()
                ->where(function ($query) use ($availableCodes) {
                    $query->whereIn('codigo', $availableCodes)
                        ->orWhereIn('external_id', $availableCodes)
                        ->orWhereIn('imobi_brasil_external_id', $availableCodes);
                })
                ->when($tenant, function ($query) use ($tenant) {
                    $query->where('tenant_id', $tenant->id);
                })
                ->get();

            $targetCodes = [];
            foreach ($availableCodes as $codigo) {
                $property = $existing->first(function (Property $property) use ($codigo) {
                    return (string) $property->codigo === (string) $codigo
                        || (string) $property->external_id === (string) $codigo
                        || (string) $property->imobi_brasil_external_id === (string) $codigo;
                });
                if (!$property || $property->trashed()) {
                    $targetCodes[] = (string) $codigo;
                }
            }

            $imported = 0;
            $restored = 0;
            $errors = 0;

            foreach ($targetCodes as $codigo) {
                $response = ImobiBrasilService::getPropertyData((int) $codigo, $tenant);
                if (!($response['success'] ?? false) || empty($response['result_set'])) {
                    $errors++;
                    Log::warning('Falha ao complementar imóvel faltante da Imobi Brasil', [
                        'tenant_id' => $tenant->id,
                        'codigo' => $codigo,
                        'error' => $response['error'] ?? 'Resposta vazia',
                    ]);
                    continue;
                }

                $persisted = $this->persistPropertyData(
                    $codigo,
                    $this->mapPropertyData($response['result_set'], true),
                    $codigo
                );

                if ($persisted['restored']) {
                    $restored++;
                }

                if ($persisted['action'] === 'created') {
                    $imported++;
                }
            }

            return [
                'success' => true,
                'available' => count($availableCodes),
                'available_codes' => $availableCodes,
                'imported' => $imported,
                'restored' => $restored,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao complementar imóveis faltantes da Imobi Brasil', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'available' => 0,
                'available_codes' => [],
                'imported' => 0,
                'restored' => 0,
                'errors' => 1,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Obter imagem destaque
     */
    private function getImagemDestaque($imagens)
    {
        if (empty($imagens)) return null;
        
        // Buscar imagem marcada como destaque
        foreach ($imagens as $img) {
            if (isset($img['destaque']) && $img['destaque']) {
                return $img['url'];
            }
        }
        
        // Se não tiver destaque, pega a primeira
        return $imagens[0]['url'] ?? null;
    }

    private function resolveCoordinates(array $imovel)
    {
        $endereco = $imovel['endereco'] ?? [];
        $logradouro = trim($endereco['logradouro'] ?? '');
        $numero = trim($endereco['numero'] ?? '');
        $bairro = trim($endereco['bairro'] ?? '');
        $cidade = trim($endereco['cidade'] ?? '');
        $estado = strtoupper(trim($endereco['estado'] ?? ''));
        $cep = preg_replace('/\D/', '', $endereco['cep'] ?? '');

        if (empty($cidade)) {
            $cidade = 'Belo Horizonte';
            $estado = $estado ?: 'MG';
        }

        $cacheKey = md5(json_encode([$logradouro, $numero, $bairro, $cidade, $estado, $cep]));
        if (isset($this->geocodeCache[$cacheKey])) {
            return $this->geocodeCache[$cacheKey];
        }

        if (empty($bairro) && empty($logradouro) && empty($cidade)) {
            return $this->geocodeCache[$cacheKey] = [null, null];
        }

        if ($cep) {
            $coords = $this->geocodeViaCep($cep);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        $queries = [];
        if ($logradouro && $numero) {
            $queries[] = "{$logradouro}, {$numero}, {$bairro}, {$cidade}, {$estado}, Brasil";
        }
        if ($logradouro) {
            $queries[] = "{$logradouro}, {$bairro}, {$cidade}, {$estado}, Brasil";
        }
        if ($bairro) {
            $queries[] = "{$bairro}, {$cidade}, {$estado}, Brasil";
        }
        $queries[] = "{$cidade}, {$estado}, Brasil";

        foreach ($queries as $query) {
            $coords = $this->searchNominatim($query);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        if ($estado) {
            $coords = $this->getStateCoordinates($estado);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        return $this->geocodeCache[$cacheKey] = [null, null];
    }

    private function geocode($endereco)
    {
        if (empty(trim($endereco))) {
            return [null, null];
        }
        return $this->searchNominatim($endereco . ', Brasil');
    }

    private function geocodeViaCep($cep)
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return [null, null];
        }

        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => "User-Agent: PropertySync/1.0\r\n"
            ]
        ]);

        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            return [null, null];
        }

        $data = json_decode($resp, true);
        if (empty($data) || !empty($data['erro'])) {
            return [null, null];
        }

        $parts = array_filter([
            $data['logradouro'] ?? null,
            $data['bairro'] ?? null,
            ($data['localidade'] ?? '') . ' - ' . ($data['uf'] ?? ''),
            'Brasil'
        ]);

        if (empty($parts)) {
            return [null, null];
        }

        $query = implode(', ', $parts);
        return $this->searchNominatim($query);
    }

    private function searchNominatim($query)
    {
        if ($this->lastGeocodeCall > 0) {
            $elapsed = microtime(true) - $this->lastGeocodeCall;
            if ($elapsed < 1.1) {
                usleep((int)((1.1 - $elapsed) * 1000000));
            }
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'br'
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: PropertySync/1.0 (contato@exclusivalarimoveis.com.br)'
            ]
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->lastGeocodeCall = microtime(true);

        if ($httpCode !== 200 || $resp === false) {
            return [null, null];
        }

        $data = json_decode($resp, true);
        if (is_array($data) && count($data) > 0) {
            $lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : null;
            $lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : null;
            return [$lat, $lng];
        }

        return [null, null];
    }

    private function validCoordinates($lat, $lng)
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        return $lat >= -33.75 && $lat <= 5.27 && $lng >= -73.99 && $lng <= -28.84;
    }

    private function getStateCoordinates($estado)
    {
        $coords = [
            'AC' => [-9.0238, -70.8120],
            'AL' => [-9.5713, -36.7820],
            'AP' => [1.4061, -51.6022],
            'AM' => [-3.4168, -65.8561],
            'BA' => [-12.5797, -41.7007],
            'CE' => [-5.4984, -39.3206],
            'DF' => [-15.7998, -47.8645],
            'ES' => [-19.1834, -40.3089],
            'GO' => [-15.8270, -49.8362],
            'MA' => [-4.9609, -45.2744],
            'MT' => [-12.6819, -56.9211],
            'MS' => [-20.7722, -54.7852],
            'MG' => [-19.9167, -43.9345],
            'PA' => [-3.7970, -52.4751],
            'PB' => [-7.2399, -36.7819],
            'PR' => [-24.8940, -51.5555],
            'PE' => [-8.8137, -36.9541],
            'PI' => [-6.6000, -42.2800],
            'RJ' => [-22.9068, -43.1729],
            'RN' => [-5.4026, -36.9541],
            'RS' => [-30.0346, -51.2177],
            'RO' => [-10.9472, -62.8278],
            'RR' => [1.3227, -60.6522],
            'SC' => [-27.2423, -50.2189],
            'SP' => [-23.5505, -46.6333],
            'SE' => [-10.5741, -37.3857],
            'TO' => [-10.1753, -48.2982],
        ];

        $estado = strtoupper($estado);
        return $coords[$estado] ?? [null, null];
    }
    
    /**
     * Fazer chamada à API da Exclusiva Lar
     */
    private function callApi($endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        
        Log::debug("API Call URL: {$url}");
        Log::debug("Token usado: " . substr($this->apiToken, 0, 10) . '...');
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: ' . $this->apiToken,
                'User-Agent: ExclusivaLar-CRM/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        Log::debug("API Response: HTTP {$httpCode}", [
            'response_length' => strlen($response),
            'has_error' => !empty($error),
            'response_preview' => substr($response, 0, 200)
        ]);
        
        if ($httpCode !== 200) {
            throw new \Exception("API retornou HTTP {$httpCode}: {$response}");
        }
        
        if ($error) {
            throw new \Exception("Erro cURL: {$error}");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Fazer chamada POST à API da Exclusiva Lar
     */
    private function callApiPost($endpoint, $postData = [])
    {
        $url = $this->baseUrl . $endpoint;
        
        Log::debug("API POST URL: {$url}");
        Log::debug("POST Data: " . json_encode($postData));
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: ' . $this->apiToken,
                'User-Agent: ExclusivaLar-CRM/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        Log::debug("API POST Response: HTTP {$httpCode}", [
            'response_length' => strlen($response),
            'has_error' => !empty($error),
            'response_preview' => substr($response, 0, 200)
        ]);
        
        if ($httpCode !== 200) {
            throw new \Exception("API retornou HTTP {$httpCode}: {$response}");
        }
        
        if ($error) {
            throw new \Exception("Erro cURL: {$error}");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Formatar descrição de imóvel com OpenAI
     */
    private function formatDescriptionWithAI($descricao)
    {
        // Se não há descrição, retornar null
        if (empty($descricao)) {
            return null;
        }

        // Verificar se a OpenAI está configurada
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            Log::warning('OpenAI API key não configurada, usando descrição original');
            // Return description with HTML entities properly encoded
            return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
        }

        try {
            $prompt = "Você é um especialista em marketing imobiliário. Formate esta descrição de imóvel de forma profissional, atrativa e organizada em HTML. Use tags HTML apropriadas como <p>, <strong>, <ul>, <li> para estruturar o conteúdo. Mantenha todas as informações importantes, mas torne-a mais vendável e bem estruturada. Use emojis apropriados. Texto original:\n\n" . $descricao;

            $data = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em marketing imobiliário. Sua função é transformar descrições de imóveis em textos atraentes, bem formatados e profissionais usando HTML. Use tags como <p>, <strong>, <ul>, <li>, <br> para formatação.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::warning('OpenAI curl error: ' . $error);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            if ($httpCode !== 200) {
                Log::warning('OpenAI HTTP error: ' . $httpCode . ' - ' . $response);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI JSON parse error: ' . json_last_error_msg());
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            if (!isset($result['choices'][0]['message']['content'])) {
                Log::warning('OpenAI response format error', ['response' => $result]);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            $textoFormatado = trim($result['choices'][0]['message']['content']);
            
            // Remove markdown code blocks if present (```html ... ```)
            $textoFormatado = preg_replace('/```html\s*(.*?)\s*```/s', '$1', $textoFormatado);
            $textoFormatado = preg_replace('/```\s*(.*?)\s*```/s', '$1', $textoFormatado);
            
            Log::info('✨ Descrição formatada com sucesso via OpenAI');
            
            return $textoFormatado;

        } catch (\Exception $e) {
            Log::error('Erro ao formatar descrição com OpenAI', [
                'error' => $e->getMessage(),
                'descricao_original' => substr($descricao, 0, 100) . '...'
            ]);
            
            // Em caso de erro, retornar descrição original com HTML decodificado
            return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
        }
    }

    /**
     * Validar se imóvel tem dados mínimos necessários
     *
     * @param array $propertyData
     * @return array ['valid' => bool, 'missing' => array]
     */
    private function validateMinimalData(array $propertyData): array
    {
        $requiredFields = [
            'codigo' => 'Código do imóvel',
            'titulo' => 'Título',
            'finalidade_imovel' => 'Finalidade',
            'tipo_imovel' => 'Tipo',
        ];

        $missing = [];

        foreach ($requiredFields as $field => $label) {
            if (empty($propertyData[$field])) {
                $missing[] = $label;
            }
        }

        // Validar se tem pelo menos um valor (venda ou aluguel)
        if (empty($propertyData['valor_venda']) && empty($propertyData['valor_aluguel'])) {
            $missing[] = 'Valor (venda ou aluguel)';
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Processar e validar imagens do imóvel
     *
     * @param array $imagens
     * @return array Imagens processadas e validadas
     */
    private function processPropertyImages(array $imagens): array
    {
        $processed = [];

        foreach ($imagens as $img) {
            $url = $img['url'] ?? null;

            if (!$url) {
                continue;
            }

            // Validar se URL é acessível (simplificado - não faz request real para não sobrecarregar)
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning('URL de imagem inválida', ['url' => $url]);
                continue;
            }

            // Validar extensão da imagem
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $validExtensions)) {
                Log::warning('Extensão de imagem não suportada', ['url' => $url, 'extension' => $extension]);
                continue;
            }

            $processed[] = [
                'url' => $url,
                'titulo' => $img['titulo'] ?? '',
                'destaque' => $img['destaque'] ?? false,
                'ordem' => $img['ordem'] ?? 999,
            ];
        }

        // Ordenar por destaque e depois por ordem
        usort($processed, function($a, $b) {
            if ($a['destaque'] != $b['destaque']) {
                return $b['destaque'] - $a['destaque']; // Destaque primeiro
            }
            return $a['ordem'] - $b['ordem'];
        });

        return $processed;
    }

    private function resolvePropertyImageUrls(array $imovel): array
    {
        $remoteUrls = [];

        if (!empty($imovel['imagens']) && is_array($imovel['imagens'])) {
            foreach ($imovel['imagens'] as $img) {
                if (isset($img['url']) && is_string($img['url']) && $img['url'] !== '') {
                    $remoteUrls[] = $img['url'];
                }
            }
        }

        $referenceCode = (string) ($imovel['referenciaImovel'] ?? '');
        $localUrls = $this->getLocalPropertyUploadUrls($referenceCode);

        if (!empty($remoteUrls) && !empty($localUrls) && $this->containsOnlyLegacyExclusivaMedia($remoteUrls)) {
            Log::warning('Usando uploads locais para substituir midia legada inacessivel', [
                'reference_code' => $referenceCode,
                'remote_count' => count($remoteUrls),
                'local_count' => count($localUrls),
                'sample_remote_url' => $remoteUrls[0] ?? null,
                'sample_local_url' => $localUrls[0] ?? null,
            ]);

            return $localUrls;
        }

        return $remoteUrls;
    }

    private function getLocalPropertyUploadUrls(string $referenceCode): array
    {
        $referenceCode = trim($referenceCode);
        if ($referenceCode === '') {
            return [];
        }

        $tenant = $this->resolveSyncTenant();
        if (!$tenant) {
            return [];
        }

        $relativeDir = "uploads/properties/tenant_{$tenant->id}/{$referenceCode}";
        $fullDir = public_path($relativeDir);
        if (!is_dir($fullDir)) {
            return [];
        }

        $files = glob($fullDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,gif,webp,JPG,JPEG,PNG,GIF,WEBP}', GLOB_BRACE) ?: [];
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values(array_map(
            static fn (string $filePath) => url(str_replace('\\', '/', $relativeDir) . '/' . basename($filePath)),
            $files
        ));
    }

    private function containsOnlyLegacyExclusivaMedia(array $urls): bool
    {
        if (empty($urls)) {
            return false;
        }

        foreach ($urls as $url) {
            if (!is_string($url) || !$this->isLegacyExclusivaMediaUrl($url)) {
                return false;
            }
        }

        return true;
    }

    private function isLegacyExclusivaMediaUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        return in_array($host, ['exclusivalarimoveis.com', 'www.exclusivalarimoveis.com.br', 'exclusivalarimoveis.com.br'], true)
            && str_contains($path, '/uploads/properties/tenant_');
    }

    /**
     * Encontrar e remover imóveis duplicados
     *
     * @param int|null $tenantId
     * @return array Estatísticas de deduplicação
     */
    public function deduplicateProperties(?int $tenantId = null): array
    {
        Log::info('🔄 Iniciando deduplicação de imóveis', ['tenant_id' => $tenantId]);

        $query = Property::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $properties = $query->get();
        $duplicates = [];

        $groups = $properties
            ->filter(fn (Property $property) => !empty($this->resolveAuthorityCode($property)))
            ->groupBy(fn (Property $property) => $this->resolveAuthorityCode($property))
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($groups as $groupKey => $group) {
            $master = $this->selectMasterProperty($group->values());
            $duplicateIds = $group
                ->where('id', '!=', $master->id)
                ->pluck('id')
                ->values()
                ->all();

            if (empty($duplicateIds)) {
                continue;
            }

            $duplicates[] = [
                'master_id' => $master->id,
                'master_codigo' => $master->codigo,
                'duplicate_ids' => $duplicateIds,
                'reason' => 'same_authority_code',
                'group_key' => (string) $groupKey,
            ];
        }

        // Mover duplicatas para a lixeira mantendo um registro mestre por código.
        $removed = 0;
        foreach ($duplicates as $duplicate) {
            foreach ($duplicate['duplicate_ids'] as $dupId) {
                try {
                    $dup = Property::find($dupId);
                    if ($dup) {
                        // Transferir matches antes de deletar
                        \App\Models\LeadPropertyMatch::where('property_id', $dupId)
                            ->update(['property_id' => $duplicate['master_id']]);

                        $duplicateTenantIds = \App\Models\PropertyPortalTenant::where('property_id', $dupId)
                            ->pluck('tenant_id')
                            ->filter()
                            ->values()
                            ->all();

                        if (!empty($duplicateTenantIds)) {
                            $conflictingTenantIds = \App\Models\PropertyPortalTenant::where('property_id', $duplicate['master_id'])
                                ->whereIn('tenant_id', $duplicateTenantIds)
                                ->pluck('tenant_id')
                                ->all();

                            if (!empty($conflictingTenantIds)) {
                                \App\Models\PropertyPortalTenant::where('property_id', $dupId)
                                    ->whereIn('tenant_id', $conflictingTenantIds)
                                    ->delete();
                            }
                        }

                        \App\Models\PropertyPortalTenant::where('property_id', $dupId)
                            ->update(['property_id' => $duplicate['master_id']]);

                        $this->trashService->moveToTrash(
                            $dup,
                            'deduplication',
                            'duplicate_codigo',
                            null,
                            [
                                'master_id' => $duplicate['master_id'],
                                'codigo' => $duplicate['master_codigo'],
                            ]
                        );
                        $removed++;

                        Log::info('Duplicata removida', [
                            'duplicate_id' => $dupId,
                            'master_id' => $duplicate['master_id'],
                            'codigo' => $duplicate['master_codigo'],
                            'reason' => $duplicate['reason'],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao remover duplicata', [
                        'duplicate_id' => $dupId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Log::info('✅ Deduplicação concluída', [
            'total_duplicates' => count($duplicates),
            'removed' => $removed
        ]);

        return [
            'success' => true,
            'total_duplicates' => count($duplicates),
            'removed' => $removed,
            'details' => $duplicates
        ];
    }

    private function selectMasterProperty($properties): Property
    {
        return $properties->sort(function (Property $left, Property $right) {
            $scoreComparison = $this->compareDuplicatePriority($right, $left);
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $left->id <=> $right->id;
        })->first();
    }

    private function compareDuplicatePriority(Property $left, Property $right): int
    {
        $leftScore = $this->getDuplicatePriorityScore($left);
        $rightScore = $this->getDuplicatePriorityScore($right);

        if ($leftScore !== $rightScore) {
            return $leftScore <=> $rightScore;
        }

        $leftTimestamp = $this->getDuplicatePriorityTimestamp($left);
        $rightTimestamp = $this->getDuplicatePriorityTimestamp($right);

        if ($leftTimestamp !== $rightTimestamp) {
            return $leftTimestamp <=> $rightTimestamp;
        }

        return $left->id <=> $right->id;
    }

    private function getDuplicatePriorityScore(Property $property): int
    {
        $score = 0;
        $score += $property->active ? 100 : 0;
        $score += $property->exibir_imovel ? 100 : 0;
        $score += $this->isNumericPropertyCode($property->codigo) ? 80 : 0;
        $score += ((string) $property->external_id !== '' && (string) $property->external_id === (string) $property->codigo) ? 60 : 0;
        $score += !empty($property->imobi_brasil_external_id) ? 40 : 0;
        $score += !empty($property->external_id) ? 20 : 0;
        $score += !empty($property->imagem_destaque) ? 10 : 0;

        if (is_array($property->imagens)) {
            $score += min(count($property->imagens), 20);
        }

        return $score;
    }

    private function isNumericPropertyCode($codigo): bool
    {
        return is_string($codigo) || is_numeric($codigo)
            ? preg_match('/^\d+$/', (string) $codigo) === 1
            : false;
    }

    private function getDuplicatePriorityTimestamp(Property $property): int
    {
        foreach ([$property->last_sync, $property->updated_at, $property->created_at] as $date) {
            if ($date instanceof \DateTimeInterface) {
                return $date->getTimestamp();
            }
        }

        return 0;
    }

    /**
     * Sincronizar um imóvel específico com retry
     *
     * @param string $codigo
     * @param int $maxRetries
     * @return array|null
     */
    public function syncSingleProperty(string $codigo, int $maxRetries = 3): ?array
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info("Sincronizando imóvel {$codigo} (tentativa {$attempt}/{$maxRetries})");

                $response = $this->callApi("/dados/{$codigo}");

                if (!isset($response['resultSet'])) {
                    throw new \Exception("Dados não encontrados para imóvel {$codigo}");
                }

                $imovel = $response['resultSet'];

                // Validar dados mínimos
                $mappedData = $this->mapPropertyData($imovel);
                $validation = $this->validateMinimalData($mappedData);

                if (!$validation['valid']) {
                    Log::warning('Imóvel com dados incompletos', [
                        'codigo' => $codigo,
                        'missing' => $validation['missing']
                    ]);

                    // Continuar mesmo com dados incompletos, mas registrar
                }

                // Verificar se já existe
                $existing = Property::where('codigo', $codigo)->first();

                if ($existing) {
                    $existing->update($mappedData);
                    Log::info("✏️ Imóvel {$codigo} atualizado");

                    return [
                        'success' => true,
                        'action' => 'updated',
                        'property_id' => $existing->id,
                        'codigo' => $codigo
                    ];
                } else {
                    $property = Property::create($mappedData);
                    Log::info("➕ Imóvel {$codigo} criado");

                    return [
                        'success' => true,
                        'action' => 'created',
                        'property_id' => $property->id,
                        'codigo' => $codigo
                    ];
                }

            } catch (\Exception $e) {
                Log::warning("Tentativa {$attempt} falhou para imóvel {$codigo}", [
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxRetries) {
                    Log::error("❌ Falha definitiva ao sincronizar imóvel {$codigo}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return null;
                }

                // Aguardar antes de retry
                sleep($attempt);
            }
        }

        return null;
    }

    /**
     * Validar e corrigir coordenadas geográficas
     *
     * @param int|null $tenantId
     * @return array
     */
    public function validateAndFixCoordinates(?int $tenantId = null): array
    {
        Log::info('🗺️ Validando coordenadas geográficas');

        $query = Property::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $properties = $query->whereNotNull('logradouro')
            ->whereNotNull('cidade')
            ->get();

        $stats = [
            'total' => $properties->count(),
            'valid' => 0,
            'invalid' => 0,
            'fixed' => 0,
            'failed' => 0
        ];

        foreach ($properties as $property) {
            $lat = $property->latitude;
            $lng = $property->longitude;

            if ($this->validCoordinates($lat, $lng)) {
                $stats['valid']++;
                continue;
            }

            $stats['invalid']++;

            // Tentar obter coordenadas
            try {
                $endereco = trim(implode(', ', array_filter([
                    $property->logradouro,
                    $property->bairro,
                    $property->cidade,
                    $property->estado
                ])));

                [$newLat, $newLng] = $this->geocode($endereco);

                if ($this->validCoordinates($newLat, $newLng)) {
                    $property->update([
                        'latitude' => $newLat,
                        'longitude' => $newLng
                    ]);

                    $stats['fixed']++;
                    Log::debug("Coordenadas corrigidas para {$property->codigo}");
                } else {
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Erro ao corrigir coordenadas", [
                    'property_id' => $property->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Respeitar rate limit
            usleep(500000); // 0.5 segundos
        }

        Log::info('✅ Validação de coordenadas concluída', $stats);

        return [
            'success' => true,
            'stats' => $stats
        ];
    }
}
