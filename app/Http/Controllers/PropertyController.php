<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\PropertySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    private $syncService;
    
    public function __construct(PropertySyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    private function resolveTenantId(Request $request): ?int
    {
        return $request->attributes->get('tenant_id')
            ?? (app()->bound('tenant') ? app('tenant')->id : null);
    }

    private function flushPortalCache(int $tenantId): void
    {
        Cache::forget("portal_imoveis_tenant_{$tenantId}");
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user();
        return $user->id ?? null;
    }

    /**
     * Lista de colunas reais da tabela de imóveis (cache por request).
     */
    private function propertyTableColumns(): array
    {
        static $columns = null;
        if ($columns === null) {
            $columns = Schema::getColumnListing((new Property())->getTable());
        }
        return $columns;
    }

    /**
     * Remove chaves que não existem no schema atual para evitar SQL 500.
     */
    private function filterToExistingPropertyColumns(array $data): array
    {
        $columns = $this->propertyTableColumns();
        $allowed = array_flip($columns);
        return array_filter(
            $data,
            static fn($value, $key) => isset($allowed[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }
    
    /**
     * Listar todos os imóveis do tenant
     * 
     * GET /api/imoveis
     */
    public function index(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }
        
        $perPage = $request->query('per_page', 15);
        
        $query = Property::where('tenant_id', $tenantId)->orderBy('created_at', 'desc');

        if ($request->boolean('published_only')) {
            $query->where('active', true)->where('exibir_imovel', true);
        }
        
        // Se pedir todos sem paginação
        if ($perPage == 100 || $perPage == 'all') {
            $properties = $query->get();
            return response()->json([
                'data' => $properties,
                'total' => $properties->count()
            ]);
        }
        
        $properties = $query->paginate($perPage);
        
        return response()->json($properties);
    }
    
    /**
     * Sincronizar imóveis manualmente
     * 
     * GET /api/properties/sync
     * 
     * IMPORTANTE: Este endpoint sincroniza APENAS os imóveis do tenant correto.
     * Deve ser chamado apenas pelo domínio do tenant (ex: exclusivalarimoveis.com)
     */
    public function sync(Request $request)
    {
        // Verificar tenant
        if (!app()->bound('tenant')) {
            return response()->json([
                'success' => false,
                'error' => 'Tenant não identificado'
            ], 403);
        }
        
        $tenant = app('tenant');
        
        // Validar tenant_id = 1
        if ($tenant->id !== 1) {
            return response()->json([
                'success' => false,
                'error' => 'Não autorizado para este tenant',
                'tenant_id' => $tenant->id
            ], 403);
        }
        
        // Executar sincronização
        try {
            $syncService = app(\App\Services\PropertySyncService::class);
            $result = $syncService->syncAll();
            $this->flushPortalCache((int) $tenant->id);
            
            return response()->json([
                'success' => true,
                'tenant_id' => $tenant->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detalhes completos de um imóvel (debug)
     * Similar ao endpoint de conversas por telefone
     * 
     * GET /api/imoveis/detalhes/{codigo}
     * 
     * Retorna TODOS os dados salvos no banco para um imóvel específico,
     * incluindo campos JSON como imagens, caracteristicas, api_data, etc.
     */
    public function detalhesCompletos(Request $request, $codigo)
    {
        try {
            $tenantId = $this->resolveTenantId($request);
            if (!$tenantId) {
                return response()->json(['error' => 'No tenant context'], 400);
            }

            // Buscar imóvel pelo código - FILTRADO POR TENANT
            $imovel = DB::table('imo_properties')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($codigo) {
                    $q->where('codigo_imovel', $codigo)
                      ->orWhere('referencia_imovel', $codigo);
                })
                ->first();
            
            if (!$imovel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Imóvel não encontrado',
                    'codigo_buscado' => $codigo
                ], 404);
            }
            
            // Decodificar campos JSON
            $imagensDecoded = null;
            if (!empty($imovel->imagens)) {
                $imagensDecoded = json_decode($imovel->imagens, true);
            }
            
            $caracteristicasDecoded = null;
            if (!empty($imovel->caracteristicas)) {
                $caracteristicasDecoded = json_decode($imovel->caracteristicas, true);
            }
            
            $apiDataDecoded = null;
            if (!empty($imovel->api_data)) {
                $apiDataDecoded = json_decode($imovel->api_data, true);
            }
            
            // Estatísticas de imagens
            $imagensStats = [
                'campo_vazio' => empty($imovel->imagens),
                'campo_null' => is_null($imovel->imagens),
                'tipo_raw' => gettype($imovel->imagens),
                'tamanho_string' => is_string($imovel->imagens) ? strlen($imovel->imagens) : null,
                'json_valido' => !empty($imovel->imagens) && json_decode($imovel->imagens) !== null,
                'total_imagens' => is_array($imagensDecoded) ? count($imagensDecoded) : 0,
                'primeira_imagem' => is_array($imagensDecoded) && count($imagensDecoded) > 0 ? $imagensDecoded[0] : null
            ];
            
            return response()->json([
                'success' => true,
                'codigo_buscado' => $codigo,
                
                // Dados principais
                'imovel' => [
                    'id' => $imovel->id,
                    'codigo_imovel' => $imovel->codigo_imovel,
                    'referencia_imovel' => $imovel->referencia_imovel,
                    'tipo_imovel' => $imovel->tipo_imovel,
                    'finalidade_imovel' => $imovel->finalidade_imovel ?? null,
                    'active' => (bool)$imovel->active,
                    'exibir_imovel' => (bool)$imovel->exibir_imovel,
                    'exclusividade' => (bool)($imovel->exclusividade ?? false),
                ],
                
                // Localização
                'localizacao' => [
                    'cidade' => $imovel->cidade ?? null,
                    'estado' => $imovel->estado ?? null,
                    'bairro' => $imovel->bairro ?? null,
                    'endereco' => $imovel->endereco ?? null,
                    'logradouro' => $imovel->logradouro ?? null,
                    'numero' => $imovel->numero ?? null,
                    'complemento' => $imovel->complemento ?? null,
                    'cep' => $imovel->cep ?? null,
                    'latitude' => $imovel->latitude ?? null,
                    'longitude' => $imovel->longitude ?? null,
                ],
                
                // Valores
                'valores' => [
                    'valor_venda' => $imovel->valor_venda ?? null,
                    'condominio' => $imovel->condominio ?? null,
                    'valor_condominio' => $imovel->valor_condominio ?? null,
                    'iptu' => $imovel->iptu ?? null,
                    'valor_iptu' => $imovel->valor_iptu ?? null,
                ],
                
                // Características
                'caracteristicas' => [
                    'dormitorios' => $imovel->dormitorios ?? null,
                    'suites' => $imovel->suites ?? null,
                    'banheiros' => $imovel->banheiros ?? null,
                    'garagem' => $imovel->garagem ?? null,
                    'area_total' => $imovel->area_total ?? null,
                    'area_privativa' => $imovel->area_privativa ?? null,
                    'area_terreno' => $imovel->area_terreno ?? null,
                    'em_condominio' => (bool)($imovel->em_condominio ?? false),
                    'nome_condominio' => $imovel->nome_condominio ?? null,
                ],
                
                // Descrição (pode ser HTML)
                'descricao' => [
                    'texto_completo' => $imovel->descricao,
                    'tamanho_caracteres' => strlen($imovel->descricao ?? ''),
                    'tem_html' => strpos($imovel->descricao ?? '', '<') !== false,
                    'preview' => substr($imovel->descricao ?? '', 0, 200) . '...',
                ],
                
                // IMAGENS (foco principal)
                'imagens' => [
                    'stats' => $imagensStats,
                    'raw_value' => $imovel->imagens,
                    'decoded' => $imagensDecoded,
                    'imagem_destaque' => $imovel->imagem_destaque,
                ],
                
                // Características detalhadas (JSON)
                'caracteristicas_json' => $caracteristicasDecoded,
                
                // Dados brutos da API (JSON)
                'api_data' => $apiDataDecoded,
                
                // Timestamps
                'timestamps' => [
                    'created_at' => $imovel->created_at,
                    'updated_at' => $imovel->updated_at,
                ],
                
                // Comparação com API (se disponível)
                'debug_info' => [
                    'total_campos_tabela' => count((array)$imovel),
                    'campos_null' => array_keys(array_filter((array)$imovel, fn($v) => is_null($v))),
                    'campos_vazios' => array_keys(array_filter((array)$imovel, fn($v) => empty($v) && !is_numeric($v) && !is_bool($v))),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5)
            ], 500);
        }
    }

    /**
     * Gera um código único para o imóvel baseado no tenant
     * Formato: {PREFIXO_TENANT}-{ANO}{SEQUENCIAL}
     * Exemplo: EXC-2026001, MAI-2026001
     */
    private function generatePropertyCode(int $tenantId): string
    {
        // Buscar prefixo do tenant (ou gerar um baseado no ID)
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        $prefix = 'IMO';
        
        if ($tenant && isset($tenant->name)) {
            // Pegar as 3 primeiras letras do nome do tenant (maiúsculas)
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $tenant->name), 0, 3));
            if (strlen($prefix) < 3) {
                $prefix = 'T' . str_pad($tenantId, 2, '0', STR_PAD_LEFT);
            }
        }
        
        $year = date('Y');
        
        // Buscar o último código do tenant neste ano
        $lastProperty = Property::where('tenant_id', $tenantId)
            ->where('codigo_imovel', 'like', "{$prefix}-{$year}%")
            ->orderBy('codigo_imovel', 'desc')
            ->first();
        
        $sequence = 1;
        if ($lastProperty) {
            // Extrair o número sequencial do código
            $lastCode = $lastProperty->codigo_imovel;
            if (preg_match('/-' . $year . '(\d+)$/', $lastCode, $matches)) {
                $sequence = intval($matches[1]) + 1;
            }
        }
        
        return sprintf('%s-%s%03d', $prefix, $year, $sequence);
    }
    
    /**
     * Upload de arquivos (imagens e vídeos)
     * Retorna array de URLs públicas
     */
    private function uploadMedia(array $files, int $tenantId, string $propertyCode): array
    {
        $uploadedUrls = [];
        $uploadPath = public_path("uploads/properties/tenant_{$tenantId}/{$propertyCode}");
        
        // Criar diretório se não existir
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        foreach ($files as $file) {
            // Validar arquivo
            if (!$file->isValid()) {
                continue;
            }
            
            // Gerar nome único
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // Mover arquivo
            $file->move($uploadPath, $filename);
            
            // Adicionar URL pública
            $uploadedUrls[] = url("uploads/properties/tenant_{$tenantId}/{$propertyCode}/{$filename}");
        }
        
        return $uploadedUrls;
    }

    /**
     * Criar novo imóvel
     * POST /api/imoveis
     * 
     * ISOLAMENTO DE TENANT REFORÇADO:
     * - tenant_id obrigatório no contexto da requisição
     * - Código gerado automaticamente com prefixo do tenant
     * - Upload de imagens isolado por tenant
     * - Validação de unicidade de código somente dentro do tenant
     */
    public function store(Request $request)
    {
        // ========== VALIDAÇÃO DE TENANT (CRÍTICO) ==========
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            \Log::warning('Property store: No tenant context', [
                'ip' => $request->ip(),
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'No tenant context'], 400);
        }

        \Log::info('Property store: Tenant validated', [
            'tenant_id' => $tenantId,
            'user_id' => $this->resolveUserId($request),
        ]);

        // ========== VALIDAÇÃO DE DADOS ==========
        $validator = Validator::make($request->all(), [
            // CÓDIGO REMOVIDO - será gerado automaticamente
            'referencia_imovel' => 'nullable|string|max:50',
            'tipo_imovel' => 'required|string|max:100',
            'finalidade_imovel' => 'required|string|in:venda,aluguel,temporada',
            'valor_venda' => 'required|numeric|min:0',
            'valor_condominio' => 'nullable|numeric|min:0',
            'valor_iptu' => 'nullable|numeric|min:0',
            'dormitorios' => 'nullable|integer|min:0',
            'suites' => 'nullable|integer|min:0',
            'banheiros' => 'nullable|integer|min:0',
            'garagem' => 'nullable|integer|min:0',
            'area_total' => 'nullable|numeric|min:0',
            'area_privativa' => 'nullable|numeric|min:0',
            'area_terreno' => 'nullable|numeric|min:0',
            'cep' => 'required|string|max:20',
            'estado' => 'required|string|max:2',
            'cidade' => 'required|string|max:100',
            'bairro' => 'required|string|max:100',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'complemento' => 'nullable|string|max:255',
            'em_condominio' => 'nullable|boolean',
            'nome_condominio' => 'nullable|string|max:255',
            'descricao' => 'nullable|string',
            'active' => 'nullable|boolean',
            'exibir_imovel' => 'nullable|boolean',
            'exclusividade' => 'nullable|boolean',
            // Upload de mídia
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,avif,jfif,heic,heif,mp4,mov,m4v,avi,webm,mkv|max:102400', // Max 100MB
            'existing_images' => 'nullable|string', // JSON array de URLs existentes
            'destaque_index' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Existem campos inválidos no formulário do imóvel.',
                'messages' => $errors,
                'errors' => $errors,
                'failed_fields' => array_keys($errors->toArray()),
            ], 422);
        }

        try {
            // ========== GERAR CÓDIGO AUTOMATICAMENTE ==========
            $codigoImovel = $this->generatePropertyCode($tenantId);
            
            \Log::info('Property store: Generated code', [
                'tenant_id' => $tenantId,
                'codigo' => $codigoImovel,
            ]);

            // ========== PREPARAR DADOS ==========
            $data = $validator->validated();
            $data['tenant_id'] = $tenantId; // FORÇAR tenant_id
            $data['codigo_imovel'] = $codigoImovel;
            // Compatibilidade com schemas antigos/novos.
            if (Schema::hasColumn((new Property())->getTable(), 'codigo')) {
                $data['codigo'] = $codigoImovel;
            }
            $data['active'] = $data['active'] ?? true;
            $data['exibir_imovel'] = $data['exibir_imovel'] ?? true;
            
            // Título automático
            $data['titulo'] = trim($request->input('titulo') ?? '');
            if ($data['titulo'] === '') {
                $tipoFormatado = ucfirst(str_replace('_', ' ', $data['tipo_imovel']));
                $data['titulo'] = "{$tipoFormatado} - {$data['bairro']}, {$data['cidade']}";
            }

            // ========== UPLOAD DE MÍDIA ==========
            $imagens = [];
            
            // Imagens existentes (modo edição)
            if ($request->has('existing_images')) {
                $existingImages = json_decode($request->input('existing_images'), true);
                if (is_array($existingImages)) {
                    $imagens = array_merge($imagens, $existingImages);
                }
            }
            
            // Novas imagens
            if ($request->hasFile('media')) {
                $uploadedUrls = $this->uploadMedia($request->file('media'), $tenantId, $codigoImovel);
                $imagens = array_merge($imagens, $uploadedUrls);
            }
            
            if (!empty($imagens)) {
                $data['imagens'] = $imagens;
                
                // Definir imagem de destaque
                $destaqueIndex = $request->input('destaque_index', 0);
                if (isset($imagens[$destaqueIndex])) {
                    $data['imagem_destaque'] = $imagens[$destaqueIndex];
                } else {
                    $data['imagem_destaque'] = $imagens[0];
                }
            }

            $rawData = $data;
            $data = $this->filterToExistingPropertyColumns($data);
            $droppedFields = array_values(array_diff(array_keys($rawData), array_keys($data)));
            if (!empty($droppedFields)) {
                \Log::warning('Property store: dropped fields not present in schema', [
                    'tenant_id' => $tenantId,
                    'fields' => $droppedFields,
                ]);
            }

            // ========== CRIAR IMÓVEL ==========
            $property = Property::create($data);
            
            \Log::info('Property created', [
                'tenant_id' => $tenantId,
                'property_id' => $property->id,
                'codigo' => $codigoImovel,
            ]);
            
            $this->flushPortalCache((int) $tenantId);

            return response()->json([
                'success' => true,
                'data' => $property,
                'message' => "Imóvel {$codigoImovel} cadastrado com sucesso!",
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Property store failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Property store failed',
                'message' => app()->environment('production')
                    ? 'Erro interno ao salvar imóvel.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Buscar imóvel por ID (restrito ao tenant)
     * GET /api/imoveis/{id}
     */
    public function show(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = Property::where('tenant_id', $tenantId)->find($id);

        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $property,
        ]);
    }

    /**
     * Atualizar imóvel
     * PUT /api/imoveis/{id}
     * 
     * ISOLAMENTO DE TENANT REFORÇADO:
     * - Somente imóveis do tenant podem ser atualizados
     * - tenant_id NUNCA pode ser alterado
     * - Upload de imagens isolado por tenant
     */
    public function update(Request $request, $id)
    {
        // ========== VALIDAÇÃO DE TENANT (CRÍTICO) ==========
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            \Log::warning('Property update: No tenant context', [
                'property_id' => $id,
                'ip' => $request->ip(),
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // ========== BUSCAR IMÓVEL (ISOLADO POR TENANT) ==========
        $property = Property::where('tenant_id', $tenantId)->find($id);

        if (!$property) {
            \Log::warning('Property update: Property not found or belongs to different tenant', [
                'property_id' => $id,
                'tenant_id' => $tenantId,
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'Property not found'], 404);
        }

        \Log::info('Property update: Tenant validated', [
            'property_id' => $id,
            'tenant_id' => $tenantId,
            'codigo' => $property->codigo_imovel,
            'user_id' => $this->resolveUserId($request),
        ]);

        // ========== VALIDAÇÃO DE DADOS ==========
        $validator = Validator::make($request->all(), [
            // CÓDIGO NÃO PODE SER ALTERADO
            'referencia_imovel' => 'nullable|string|max:50',
            'tipo_imovel' => 'nullable|string|max:100',
            'finalidade_imovel' => 'nullable|string|in:venda,aluguel,temporada',
            'valor_venda' => 'nullable|numeric|min:0',
            'valor_condominio' => 'nullable|numeric|min:0',
            'valor_iptu' => 'nullable|numeric|min:0',
            'dormitorios' => 'nullable|integer|min:0',
            'suites' => 'nullable|integer|min:0',
            'banheiros' => 'nullable|integer|min:0',
            'garagem' => 'nullable|integer|min:0',
            'area_total' => 'nullable|numeric|min:0',
            'area_privativa' => 'nullable|numeric|min:0',
            'area_terreno' => 'nullable|numeric|min:0',
            'cep' => 'nullable|string|max:20',
            'estado' => 'nullable|string|max:2',
            'cidade' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'complemento' => 'nullable|string|max:255',
            'em_condominio' => 'nullable|boolean',
            'nome_condominio' => 'nullable|string|max:255',
            'descricao' => 'nullable|string',
            'active' => 'nullable|boolean',
            'exibir_imovel' => 'nullable|boolean',
            'exclusividade' => 'nullable|boolean',
            // Upload de mídia
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,avif,jfif,heic,heif,mp4,mov,m4v,avi,webm,mkv|max:102400', // Max 100MB
            'existing_images' => 'nullable|string', // JSON array de URLs existentes
            'destaque_index' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Existem campos inválidos no formulário do imóvel.',
                'messages' => $errors,
                'errors' => $errors,
                'failed_fields' => array_keys($errors->toArray()),
            ], 422);
        }

        try {
            // ========== PREPARAR DADOS ==========
            $data = $validator->validated();
            
            // GARANTIR que tenant_id NUNCA seja alterado
            unset($data['tenant_id']);
            
            // Atualizar título se fornecido
            if (array_key_exists('titulo', $request->all())) {
                $data['titulo'] = trim($request->input('titulo'));
                if ($data['titulo'] === '' && isset($data['tipo_imovel']) && isset($data['bairro']) && isset($data['cidade'])) {
                    $tipoFormatado = ucfirst(str_replace('_', ' ', $data['tipo_imovel']));
                    $data['titulo'] = "{$tipoFormatado} - {$data['bairro']}, {$data['cidade']}";
                }
            }

            // ========== UPLOAD DE MÍDIA ==========
            $imagens = $property->imagens ?? [];
            
            // Se houver existing_images, substituir completamente
            if ($request->has('existing_images')) {
                $existingImages = json_decode($request->input('existing_images'), true);
                if (is_array($existingImages)) {
                    $imagens = $existingImages;
                } else {
                    $imagens = [];
                }
            }
            
            // Novas imagens
            if ($request->hasFile('media')) {
                $uploadedUrls = $this->uploadMedia(
                    $request->file('media'), 
                    $tenantId, 
                    $property->codigo_imovel
                );
                $imagens = array_merge($imagens, $uploadedUrls);
            }
            
            if (!empty($imagens)) {
                $data['imagens'] = $imagens;
                
                // Definir imagem de destaque
                $destaqueIndex = $request->input('destaque_index');
                if ($destaqueIndex !== null && isset($imagens[$destaqueIndex])) {
                    $data['imagem_destaque'] = $imagens[$destaqueIndex];
                } elseif (!isset($property->imagem_destaque) || !in_array($property->imagem_destaque, $imagens)) {
                    // Se não tem destaque ou o destaque atual não está nas imagens, usar a primeira
                    $data['imagem_destaque'] = $imagens[0];
                }
            }

            $rawData = $data;
            $data = $this->filterToExistingPropertyColumns($data);
            $droppedFields = array_values(array_diff(array_keys($rawData), array_keys($data)));
            if (!empty($droppedFields)) {
                \Log::warning('Property update: dropped fields not present in schema', [
                    'tenant_id' => $tenantId,
                    'property_id' => $id,
                    'fields' => $droppedFields,
                ]);
            }

            // ========== ATUALIZAR IMÓVEL ==========
            $property->update($data);
            
            \Log::info('Property updated', [
                'property_id' => $id,
                'tenant_id' => $tenantId,
                'codigo' => $property->codigo_imovel,
                'user_id' => $this->resolveUserId($request),
            ]);
            
            $this->flushPortalCache((int) $tenantId);

            return response()->json([
                'success' => true,
                'data' => $property->fresh(),
                'message' => "Imóvel {$property->codigo_imovel} atualizado com sucesso!",
            ]);
        } catch (\Throwable $e) {
            \Log::error('Property update failed', [
                'tenant_id' => $tenantId,
                'property_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Property update failed',
                'message' => app()->environment('production')
                    ? 'Erro interno ao atualizar imóvel.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Excluir imóvel (restrito ao tenant)
     * DELETE /api/imoveis/{id}
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = Property::where('tenant_id', $tenantId)->find($id);

        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $property->delete();
        $this->flushPortalCache((int) $tenantId);

        return response()->json([
            'success' => true,
            'message' => 'Imóvel excluído com sucesso',
        ]);
    }

    /**
     * Exportar imóveis para CSV
     * GET /api/imoveis/export
     */
    public function export()
    {
        try {
            $tenantId = app()->bound('tenant') ? app('tenant')->id : null;
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tenant context'
                ], 400);
            }

            $properties = DB::table('imo_properties')
                ->select([
                    'codigo_imovel',
                    'referencia_imovel',
                    'tipo_imovel',
                    'finalidade_imovel',
                    'valor_venda',
                    'cidade',
                    'bairro',
                    'dormitorios',
                    'suites',
                    'banheiros',
                    'garagem',
                    'area_total',
                    'active',
                    'created_at'
                ])
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get();

            $csv = [];
            $csv[] = [
                'Código',
                'Referência',
                'Tipo',
                'Finalidade',
                'Valor',
                'Cidade',
                'Bairro',
                'Dormitórios',
                'Suítes',
                'Banheiros',
                'Garagem',
                'Área Total',
                'Ativo',
                'Data Cadastro'
            ];

            foreach ($properties as $property) {
                $csv[] = [
                    $property->codigo_imovel,
                    $property->referencia_imovel ?? '',
                    $property->tipo_imovel ?? '',
                    $property->finalidade_imovel ?? '',
                    $property->valor_venda ?? '',
                    $property->cidade ?? '',
                    $property->bairro ?? '',
                    $property->dormitorios ?? '',
                    $property->suites ?? '',
                    $property->banheiros ?? '',
                    $property->garagem ?? '',
                    $property->area_total ?? '',
                    $property->active ? 'Sim' : 'Não',
                    $property->created_at ?? ''
                ];
            }

            $filename = 'imoveis_' . date('Y-m-d_His') . '.csv';
            $handle = fopen('php://temp', 'r+');

            foreach ($csv as $row) {
                fputcsv($handle, $row, ';');
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return response($content)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar descrição de propaganda com IA para um imóvel
     * 
     * POST /api/properties/{id}/generate-ad-description
     */
    public function generateAdDescription(Request $request, $id)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            
            if (!$tenantId) {
                return response()->json(['error' => 'No tenant context'], 400);
            }
            
            $property = Property::where('tenant_id', $tenantId)
                ->findOrFail($id);

            // Buscar configuração do tenant para pegar a chave OpenAI
            $tenantConfig = DB::table('tenant_configs')
                ->where('tenant_id', $tenantId)
                ->first();

            $openaiKey = null;
            
            // Procurar chave OpenAI nas configurações
            if ($tenantConfig) {
                // Tentar nas configurações gerais
                if ($tenantConfig->settings) {
                    $settings = json_decode($tenantConfig->settings, true);
                    $openaiKey = $settings['openai_api_key'] ?? null;
                }
                
                // Se não encontrar, procurar no env específico do tenant
                if (!$openaiKey) {
                    $envKey = strtoupper($tenantConfig->slug ?? '') . '_OPENAI_API_KEY';
                    $openaiKey = env($envKey);
                }
            }

            // Fallback para chave global
            if (!$openaiKey) {
                $openaiKey = env('OPENAI_API_KEY');
            }

            if (!$openaiKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chave da OpenAI não configurada para este tenant'
                ], 400);
            }

            // Preparar informações do imóvel
            $propertyInfo = [
                'title' => $property->titulo ?? $property->type ?? 'Imóvel',
                'type' => $property->type ?? $property->tipo_imovel ?? '',
                'city' => $property->city ?? $property->cidade ?? '',
                'state' => $property->state ?? $property->estado ?? '',
                'neighborhood' => $property->neighborhood ?? $property->bairro ?? '',
                'bedrooms' => $property->bedrooms ?? $property->dormitorios ?? 0,
                'bathrooms' => $property->bathrooms ?? $property->banheiros ?? 0,
                'area' => $property->area ?? $property->area_total ?? 0,
                'price' => $property->price ?? $property->valor_venda ?? 0,
                'description' => $property->descricao ?? '',
            ];

            // Construir prompt para a IA
            $location = trim("{$propertyInfo['neighborhood']}, {$propertyInfo['city']}/{$propertyInfo['state']}", ', ');
            
            $prompt = "Crie uma descrição CURTA e ATRATIVA para propaganda de imóvel nas redes sociais (Instagram/WhatsApp).

IMPORTANTE: Máximo de 400 caracteres. Use EXATAMENTE a localização informada abaixo.

📍 Localização: {$location}
🏠 Tipo: {$propertyInfo['type']}
🛏️ Quartos: {$propertyInfo['bedrooms']}
🚿 Banheiros: {$propertyInfo['bathrooms']}
📐 Área: {$propertyInfo['area']}m²
💰 Valor: R$ " . number_format($propertyInfo['price'], 2, ',', '.') . "

REGRAS:
- Use OBRIGATORIAMENTE a cidade '{$propertyInfo['city']}' no texto
- Máximo 400 caracteres
- Tom persuasivo e moderno
- Destaque a localização e diferenciais
- Inclua call-to-action

Responda APENAS com o texto da propaganda, sem aspas ou formatação adicional.";

            // Fazer requisição para OpenAI
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openaiKey
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um especialista em marketing imobiliário que cria descrições curtas e atrativas para redes sociais.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 150,
                    'temperature' => 0.7
                ])
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                \Log::error('OpenAI API Error: ' . $response);
                
                // Fallback: criar descrição manual
                $parts = [];
                if ($propertyInfo['type']) $parts[] = ucfirst($propertyInfo['type']);
                if ($propertyInfo['bedrooms']) $parts[] = "{$propertyInfo['bedrooms']} quartos";
                if ($propertyInfo['bathrooms']) $parts[] = "{$propertyInfo['bathrooms']} banheiros";
                if ($propertyInfo['area']) $parts[] = "{$propertyInfo['area']}m²";
                if ($propertyInfo['neighborhood']) $parts[] = $propertyInfo['neighborhood'];
                if ($propertyInfo['city']) $parts[] = $propertyInfo['city'];
                
                $description = implode(', ', $parts) . '. Entre em contato!';
                
                return response()->json([
                    'success' => true,
                    'description' => substr($description, 0, 400),
                    'fallback' => true
                ]);
            }

            $result = json_decode($response, true);
            $description = $result['choices'][0]['message']['content'] ?? '';
            
            // Garantir limite de 400 caracteres
            $description = substr(trim($description), 0, 400);

            return response()->json([
                'success' => true,
                'description' => $description
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generating ad description: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar descrição: ' . $e->getMessage()
            ], 500);
        }
    }
}
