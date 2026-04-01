<?php
namespace App\Http\Controllers;

use App\Models\PropertyDocument;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Jobs\SendImobiBrasilImagesJob;
use App\Services\PropertyTrashService;
use App\Services\PropertySyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    private $syncService;
    private PropertyTrashService $trashService;
    
    public function __construct(PropertySyncService $syncService, PropertyTrashService $trashService)
    {
        $this->syncService = $syncService;
        $this->trashService = $trashService;
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

    private function flushPortalCaches(array $tenantIds): void
    {
        foreach (array_unique(array_map('intval', $tenantIds)) as $tenantId) {
            if ($tenantId > 0) {
                $this->flushPortalCache($tenantId);
            }
        }
    }

    private function getAssociatedTenantIds(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_associations')) {
            return [];
        }

        $direct = DB::table('tenant_associations')
            ->where('tenant_id', $tenantId)
            ->pluck('associated_tenant_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $inverse = DB::table('tenant_associations')
            ->where('associated_tenant_id', $tenantId)
            ->pluck('tenant_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        return array_values(array_unique(array_filter(
            array_merge($direct, $inverse),
            fn ($id) => $id > 0 && $id !== $tenantId
        )));
    }

    private function getAllowedPortalTenantIds(int $tenantId): array
    {
        $candidateIds = array_values(array_unique(array_merge([$tenantId], $this->getAssociatedTenantIds($tenantId))));

        return Tenant::query()
            ->whereIn('id', $candidateIds)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function normalizePortalTenantIds(int $ownerTenantId, $portalTenantIds): array
    {
        $selectedIds = collect(is_array($portalTenantIds) ? $portalTenantIds : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->toArray();

        $allowedIds = $this->getAllowedPortalTenantIds($ownerTenantId);
        $selectedIds = array_values(array_intersect($selectedIds, $allowedIds));

        if (empty($selectedIds)) {
            $selectedIds = [$ownerTenantId];
        }

        return array_values(array_unique($selectedIds));
    }

    private function getPropertyPortalTenantIds(int $propertyId, int $ownerTenantId): array
    {
        if (!Schema::hasTable('property_portal_tenants')) {
            return [$ownerTenantId];
        }

        $ids = DB::table('property_portal_tenants')
            ->where('property_id', $propertyId)
            ->pluck('tenant_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (empty($ids)) {
            return [$ownerTenantId];
        }

        return array_values(array_unique($ids));
    }

    private function syncPropertyPortalTenantIds(int $propertyId, int $ownerTenantId, array $portalTenantIds): array
    {
        if (!Schema::hasTable('property_portal_tenants')) {
            return [$ownerTenantId];
        }

        $normalizedIds = $this->normalizePortalTenantIds($ownerTenantId, $portalTenantIds);
        DB::table('property_portal_tenants')->where('property_id', $propertyId)->delete();

        $now = Carbon::now();
        $rows = array_map(static fn ($tenantId) => [
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $normalizedIds);
        DB::table('property_portal_tenants')->insert($rows);

        return $normalizedIds;
    }

    private function resolveUserId(Request $request): ?int
    {
        $user = $request->user();
        return $user->id ?? null;
    }

    private function resolveCaptadorData(int $tenantId, mixed $captadorUserId): array
    {
        $captadorId = (int) $captadorUserId;
        if ($captadorId <= 0) {
            return [null, null];
        }

        $captador = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['corretor', 'admin'])
            ->where('id', $captadorId)
            ->select('id', 'name')
            ->first();

        if (!$captador) {
            return [null, null];
        }

        return [(int) $captador->id, (string) $captador->name];
    }

    private function hydratePropertyUserNames(iterable $properties, int $tenantId): void
    {
        $ids = [];

        foreach ($properties as $property) {
            $captadorId = (int) ($property->captador_user_id ?? 0);
            $inseridoPorId = (int) ($property->inserido_por_user_id ?? 0);

            if ($captadorId > 0) {
                $ids[] = $captadorId;
            }

            if ($inseridoPorId > 0) {
                $ids[] = $inseridoPorId;
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
        if (empty($ids)) {
            return;
        }

        $usersById = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        foreach ($properties as $property) {
            $captadorId = (int) ($property->captador_user_id ?? 0);
            $inseridoPorId = (int) ($property->inserido_por_user_id ?? 0);

            if ($captadorId > 0 && isset($usersById[$captadorId])) {
                $property->captador_nome = $usersById[$captadorId];
            }

            if ($inseridoPorId > 0 && isset($usersById[$inseridoPorId])) {
                $property->inserido_por_nome = $usersById[$inseridoPorId];
            }
        }
    }

    private function resolveTenantOpenAiKey(int $tenantId): ?string
    {
        $tenantConfig = DB::table('tenant_configs')
            ->where('tenant_id', $tenantId)
            ->first();

        $openaiKey = null;
        if ($tenantConfig) {
            if (!empty($tenantConfig->settings)) {
                $settings = json_decode($tenantConfig->settings, true);
                if (is_array($settings)) {
                    $openaiKey = $settings['openai_api_key'] ?? null;
                }
            }

            if (!$openaiKey) {
                $slug = $tenantConfig->slug ?? '';
                if ($slug) {
                    $envKey = strtoupper($slug) . '_OPENAI_API_KEY';
                    $openaiKey = env($envKey);
                }
            }
        }

        if (!$openaiKey) {
            $openaiKey = env('OPENAI_API_KEY');
        }

        return $openaiKey ?: null;
    }

    private function fallbackDescriptions(array $payload): array
    {
        $tipo = $payload['tipo_imovel'] ?? 'imovel';
        $finalidade = (string) ($payload['finalidade_imovel'] ?? 'venda');
        $quartos = (int) ($payload['dormitorios'] ?? 0);
        $banheiros = (int) ($payload['banheiros'] ?? 0);
        $area = $payload['area_total'] ?? null;
        $bairro = $payload['bairro'] ?? '';
        $cidade = $payload['cidade'] ?? '';
        $estado = $payload['estado'] ?? '';
        $valor = str_contains($finalidade, 'aluguel') || str_contains($finalidade, 'temporada')
            ? (float) ($payload['valor_aluguel'] ?? ($payload['valor_venda'] ?? 0))
            : (float) ($payload['valor_venda'] ?? ($payload['valor_aluguel'] ?? 0));

        $headline = ucfirst(str_replace('_', ' ', $tipo));
        $descricao = "{$headline} em {$bairro}, {$cidade}/{$estado}.";
        if ($quartos > 0) {
            $descricao .= " {$quartos} quartos.";
        }
        if ($banheiros > 0) {
            $descricao .= " {$banheiros} banheiros.";
        }
        if ($area) {
            $descricao .= " {$area}m² de área.";
        }
        if ($valor > 0) {
            $descricao .= " " . (str_contains($finalidade, 'aluguel') || str_contains($finalidade, 'temporada') ? 'Valor de locação' : 'Valor') . ": R$ " . number_format($valor, 2, ',', '.') . ".";
        }
        $descricao .= " Entre em contato para mais detalhes e agendamento de visita.";

        $resumo = trim("{$headline} | {$bairro}, {$cidade}/{$estado}" . ($quartos > 0 ? " | {$quartos}q" : '') . ($valor > 0 ? " | R$ " . number_format($valor, 0, ',', '.') : ''));

        return [
            'descricao' => trim($descricao),
            'descricao_resumida' => substr($resumo, 0, 220),
            'fallback' => true,
        ];
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

    private function normalizeStringListInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $items = $value;
        } else {
            $raw = trim((string) $value);
            if ($raw === '') {
                return json_encode([], JSON_UNESCAPED_UNICODE);
            }

            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        $normalized = array_values(array_unique(array_filter(array_map(static function ($item) {
            if (!is_scalar($item)) {
                return '';
            }

            return trim((string) $item);
        }, $items), static fn ($item) => $item !== '')));

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    private function normalizeStructuredPropertyData(array $data): array
    {
        foreach (['caracteristicas', 'classificacoes'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeStringListInput($data[$field]);
            }
        }

        return $data;
    }

    private function applyPropertyNumericDefaults(array $data): array
    {
        $numericDefaults = [
            'valor_venda' => 0,
            'valor_aluguel' => 0,
            'valor_condominio' => 0,
            'valor_iptu' => 0,
            'dormitorios' => 0,
            'suites' => 0,
            'banheiros' => 0,
            'garagem' => 0,
            'area_total' => 0,
            'area_privativa' => 0,
            'area_terreno' => 0,
        ];

        foreach ($numericDefaults as $field => $defaultValue) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                $data[$field] = $defaultValue;
            }
        }

        return $data;
    }

    private function appendPropertyBusinessRulesValidation(\Illuminate\Validation\Validator $validator, Request $request, bool $isUpdate = false): void
    {
        $validator->after(function ($validator) use ($request, $isUpdate) {
            $purpose = strtolower((string) $request->input('finalidade_imovel', ''));

            if ($purpose === '' && $isUpdate) {
                return;
            }

            $requiresSalePrice = in_array($purpose, ['venda', 'venda_aluguel'], true);
            $requiresRentPrice = in_array($purpose, ['aluguel', 'temporada', 'venda_aluguel', 'aluguel_temporada'], true);

            if ($requiresSalePrice && !is_numeric($request->input('valor_venda'))) {
                $validator->errors()->add('valor_venda', 'Informe o valor de venda para a finalidade selecionada.');
            }

            if ($requiresRentPrice && !is_numeric($request->input('valor_aluguel'))) {
                $validator->errors()->add('valor_aluguel', 'Informe o valor de aluguel para a finalidade selecionada.');
            }

            foreach (['caracteristicas', 'classificacoes'] as $field) {
                $rawValue = $request->input($field);
                if ($rawValue === null || $rawValue === '') {
                    continue;
                }

                $normalized = $this->normalizeStringListInput($rawValue);
                $decoded = json_decode((string) $normalized, true);
                if (!is_array($decoded)) {
                    $validator->errors()->add($field, 'O campo deve ser uma lista válida.');
                }
            }
        });
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
        $scope = $request->query('scope', 'active');
        $authUser = $request->user();
        $isTrainee = $authUser?->role === 'trainee';
        $proprietarioCols = ['proprietario_nome', 'proprietario_telefone', 'proprietario_email', 'proprietario_observacoes'];
        
        $query = Property::withTrashed()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('deleted_at')
            ->orderBy('created_at', 'desc');

        if ($scope === 'trash') {
            $query->onlyTrashed();
        } elseif ($scope !== 'all') {
            $query->whereNull('deleted_at');
        }

        if ($scope !== 'trash' && $request->boolean('published_only')) {
            $query->where('active', true)->where('exibir_imovel', true);
        }
        
        // Se pedir todos sem paginação
        if ($perPage == 100 || $perPage == 'all') {
            $properties = $query->get();
            $this->hydratePropertyUserNames($properties, (int) $tenantId);
            if ($isTrainee) {
                $properties->each->makeHidden($proprietarioCols);
            }
            return response()->json([
                'data' => $properties,
                'total' => $properties->count(),
                'scope' => $scope,
            ]);
        }
        
        $properties = $query->paginate($perPage);
        $this->hydratePropertyUserNames($properties->getCollection(), (int) $tenantId);
        if ($isTrainee) {
            $properties->getCollection()->each->makeHidden($proprietarioCols);
        }
        
        return response()->json($properties);
    }

    public function captadores(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $captadores = User::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['corretor', 'admin'])
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'success' => true,
            'captadores' => $captadores,
        ]);
    }

    public function restore(Request $request, $id)
    {
        $authUser = $request->user();
        if (!in_array($authUser?->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'error' => 'Apenas administradores podem restaurar imóveis'], 403);
        }

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = Property::withTrashed()->where('tenant_id', $tenantId)->onlyTrashed()->find($id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $restored = $this->trashService->restoreFromTrash($property);
        $portalTenantIds = $this->getPropertyPortalTenantIds($restored->id, (int) $tenantId);
        $this->flushPortalCaches($portalTenantIds);

        return response()->json([
            'success' => true,
            'message' => 'Imóvel restaurado com sucesso',
            'data' => $restored,
        ]);
    }

    public function forceDestroy(Request $request, $id)
    {
        $authUser = $request->user();
        if (!in_array($authUser?->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'error' => 'Apenas administradores podem excluir definitivamente imóveis'], 403);
        }

        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = Property::withTrashed()->where('tenant_id', $tenantId)->onlyTrashed()->find($id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $portalTenantIds = $this->getPropertyPortalTenantIds($property->id, (int) $tenantId);

        try {
            $this->trashService->forceDelete($property);
            $this->flushPortalCaches($portalTenantIds);

            return response()->json([
                'success' => true,
                'message' => 'Imóvel removido definitivamente com sucesso',
            ]);
        } catch (\Throwable $e) {
            Log::error('Property force delete failed', [
                'tenant_id' => $tenantId,
                'property_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error' => app()->environment('production')
                    ? 'Erro interno ao excluir definitivamente o imóvel.'
                    : $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista os portais permitidos para publicação do imóvel no tenant atual.
     * GET /api/imoveis/portal-opcoes
     */
    public function portalOptions(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $allowedIds = $this->getAllowedPortalTenantIds((int) $tenantId);
        $tenants = Tenant::query()
            ->whereIn('id', $allowedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'slug'])
            ->map(function ($tenant) use ($tenantId) {
                return [
                    'id' => (int) $tenant->id,
                    'name' => $tenant->name,
                    'domain' => $tenant->domain,
                    'slug' => $tenant->slug,
                    'is_owner' => (int) $tenant->id === (int) $tenantId,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
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
        $relativePath = "uploads/properties/tenant_{$tenantId}/{$propertyCode}";
        $publicUploadPath = public_path($relativePath);
        $storageUploadPath = storage_path("app/public/{$relativePath}");

        $uploadPath = null;
        $baseUrl = null;

        // Prioridade: manter compatibilidade com links já existentes em /uploads.
        foreach ([
            ['path' => $publicUploadPath, 'base_url' => url($relativePath)],
            ['path' => $storageUploadPath, 'base_url' => url("storage/{$relativePath}")],
        ] as $candidate) {
            $candidatePath = $candidate['path'];

            if (!is_dir($candidatePath) && !@mkdir($candidatePath, 0755, true) && !is_dir($candidatePath)) {
                continue;
            }

            if (is_writable($candidatePath)) {
                $uploadPath = $candidatePath;
                $baseUrl = rtrim($candidate['base_url'], '/');
                break;
            }
        }

        if (!$uploadPath || !$baseUrl) {
            throw new \RuntimeException(
                "Nao foi possivel preparar diretorio de upload. " .
                "Tentativas: {$publicUploadPath} e {$storageUploadPath}"
            );
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

            // Aplicar marca d'água do tenant (se configurada e imagem suportada)
            $imageExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array(strtolower($extension), $imageExts)) {
                $tenant = \App\Models\Tenant::find($tenantId);
                if ($tenant && $tenant->watermark_url) {
                    $localWm = ltrim($tenant->watermark_url, '/');
                    // try public path first, then resolve via URL helper
                    $wmPath = public_path($localWm);
                    if (!file_exists($wmPath)) {
                        $wmPath = $tenant->watermark_url; // may be absolute or full URL
                    }
                    \App\Services\WatermarkService::apply($uploadPath . '/' . $filename, $wmPath);
                }
            }

            // Adicionar URL pública
            $uploadedUrls[] = "{$baseUrl}/{$filename}";
        }
        
        return $uploadedUrls;
    }

    /**
     * Normaliza o payload de upload para sempre retornar uma lista de arquivos.
     */
    private function normalizeUploadedFiles($files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }
        if (is_array($files)) {
            return array_values(array_filter($files, static fn($file) => $file instanceof UploadedFile));
        }
        return [];
    }

    private function findTenantProperty(Request $request, $id): ?Property
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return null;
        }

        return Property::where('tenant_id', $tenantId)->find($id);
    }

    /**
     * GET /api/imoveis/{id}/documentos
     */
    public function listDocuments(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = $this->findTenantProperty($request, $id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $documents = PropertyDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('property_id', $property->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * POST /api/imoveis/{id}/documentos
     */
    public function uploadDocument(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = $this->findTenantProperty($request, $id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'arquivo' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,txt|max:20480',
            'nome' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('arquivo');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $propertyCode = $property->codigo_imovel ?: ('property_' . $property->id);
        $path = Storage::disk('public')->putFileAs(
            'properties/' . $tenantId . '/' . $propertyCode . '/documents',
            $file,
            Str::uuid() . '.' . $extension
        );

        $document = PropertyDocument::create([
            'tenant_id' => $tenantId,
            'property_id' => $property->id,
            'tipo' => trim((string) $request->input('tipo', '')) ?: null,
            'nome' => trim((string) $request->input('nome', '')) ?: $file->getClientOriginalName(),
            'arquivo_path' => $path,
            'mime_type' => $file->getMimeType(),
            'tamanho_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $this->resolveUserId($request),
        ]);

        return response()->json([
            'success' => true,
            'data' => $document,
        ], 201);
    }

    /**
     * DELETE /api/imoveis/{id}/documentos/{documentoId}
     */
    public function deleteDocument(Request $request, $id, $documentoId)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = $this->findTenantProperty($request, $id);
        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $document = PropertyDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('property_id', $property->id)
            ->find($documentoId);

        if (!$document) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        Storage::disk('public')->delete($document->arquivo_path);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento removido com sucesso',
        ]);
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
            Log::warning('Property store: No tenant context', [
                'ip' => $request->ip(),
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'No tenant context'], 400);
        }

        Log::info('Property store: Tenant validated', [
            'tenant_id' => $tenantId,
            'user_id' => $this->resolveUserId($request),
        ]);

        // ========== VALIDAÇÃO DE DADOS ==========
        $validator = Validator::make($request->all(), [
            // CÓDIGO REMOVIDO - será gerado automaticamente
            'referencia_imovel' => 'nullable|string|max:50',
            'tipo_imovel' => 'required|string|max:100',
            'finalidade_imovel' => 'required|string|in:venda,aluguel,temporada,venda_aluguel,aluguel_temporada',
            'valor_venda' => 'nullable|numeric|min:0',
            'valor_aluguel' => 'nullable|numeric|min:0',
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
            'descricao_resumida' => 'nullable|string|max:1000',
            'caracteristicas' => 'nullable|string',
            'classificacoes' => 'nullable|string',
            'local_chaves' => 'nullable|string|max:255',
            'status_chaves' => 'nullable|string|in:disponivel,retirada,reserva',
            'visibilidade_endereco' => 'nullable|string|in:completo,bairro_cidade,cidade_estado,oculto',
            'portal_tenant_ids' => 'nullable|array',
            'portal_tenant_ids.*' => 'integer|exists:tenants,id',
            'active' => 'nullable|boolean',
            'exibir_imovel' => 'nullable|boolean',
            'destaque' => 'nullable|boolean',
            'exclusividade' => 'nullable|boolean',
            // Upload de mídia
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,avif,jfif,heic,heif,mp4,mov,m4v,avi,webm,mkv|max:102400', // Max 100MB
            'existing_images' => 'nullable|string', // JSON array de URLs existentes
            'destaque_index' => 'nullable|integer',
            'captador_user_id' => 'nullable|integer',
            'construtora_pessoa_id' => 'nullable|integer',
            // Dados do proprietário (interno)
            'proprietario_nome' => 'nullable|string|max:150',
            'proprietario_telefone' => 'nullable|string|max:30',
            'proprietario_email' => 'nullable|email|max:150',
            'proprietario_observacoes' => 'nullable|string',
        ]);

        $this->appendPropertyBusinessRulesValidation($validator, $request);

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
            
            Log::info('Property store: Generated code', [
                'tenant_id' => $tenantId,
                'codigo' => $codigoImovel,
            ]);

            // ========== PREPARAR DADOS ==========
            $data = $this->applyPropertyNumericDefaults(
                $this->normalizeStructuredPropertyData($validator->validated())
            );
            [$captadorUserId, $captadorNome] = $this->resolveCaptadorData($tenantId, $data['captador_user_id'] ?? null);
            $data['captador_user_id'] = $captadorUserId;
            $data['captador_nome'] = $captadorNome;
            
            // ========== VALIDAÇÃO DE PORTAL_TENANT_IDS (APENAS TENANTS ASSOCIADOS) ==========
            // Portal tenant IDs devem incluir apenas tenants associados ao tenant atual
            $portalTenantIds = [];
            if (isset($data['portal_tenant_ids']) && !empty($data['portal_tenant_ids'])) {
                $allowedTenantIds = $this->getAllowedPortalTenantIds($tenantId);
                $requestedIds = is_array($data['portal_tenant_ids']) ? $data['portal_tenant_ids'] : [];
                
                // Filtrar apenas os tenants associados (que não seja o próprio tenant)
                $portalTenantIds = array_filter(
                    $requestedIds,
                    fn ($id) => in_array((int)$id, $allowedTenantIds) && (int)$id !== (int)$tenantId
                );
            }
            unset($data['portal_tenant_ids']);
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

            // ========== REGISTRAR QUEM INSERIU (somente no cadastro) ==========
            $insertingUser = $request->user();
            $data['inserido_por_user_id'] = $insertingUser->id ?? 102;
            $data['inserido_por_nome'] = $insertingUser->name ?? 'Jocineide Lima';

            // ========== UPLOAD DE MÍDIA ==========
            $imagens = [];
            
            // Imagens existentes (modo edição)
            if ($request->has('existing_images')) {
                $existingImages = json_decode($request->input('existing_images'), true);
                if (is_array($existingImages)) {
                    $imagens = array_merge($imagens, $existingImages);
                }
            }
            
            $mediaUploadWarning = null;

            // Novas imagens
            if ($request->hasFile('media')) {
                try {
                    $uploadedFiles = $this->normalizeUploadedFiles($request->file('media'));
                    if (!empty($uploadedFiles)) {
                        $uploadedUrls = $this->uploadMedia($uploadedFiles, $tenantId, $codigoImovel);
                        $imagens = array_merge($imagens, $uploadedUrls);
                    }
                } catch (\Throwable $uploadError) {
                    Log::error('Property store: media upload failed, continuing without media', [
                        'tenant_id' => $tenantId,
                        'codigo' => $codigoImovel,
                        'error' => $uploadError->getMessage(),
                        'file' => $uploadError->getFile(),
                        'line' => $uploadError->getLine(),
                    ]);
                    $mediaUploadWarning = 'Imóvel salvo, mas ocorreu falha no upload da mídia.';
                }
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
                Log::warning('Property store: dropped fields not present in schema', [
                    'tenant_id' => $tenantId,
                    'fields' => $droppedFields,
                ]);
            }

            // ========== CRIAR IMÓVEL ==========
            $property = Property::create($data);
            $selectedPortalTenantIds = $this->syncPropertyPortalTenantIds($property->id, (int) $tenantId, is_array($portalTenantIds) ? $portalTenantIds : []);
            
            Log::info('Property created', [
                'tenant_id' => $tenantId,
                'property_id' => $property->id,
                'codigo' => $codigoImovel,
            ]);
            
            $this->flushPortalCaches($selectedPortalTenantIds);

            return response()->json([
                'success' => true,
                'data' => array_merge($property->toArray(), [
                    'portal_tenant_ids' => $selectedPortalTenantIds,
                ]),
                'message' => $mediaUploadWarning ?: "Imóvel {$codigoImovel} cadastrado com sucesso!",
                'upload_warning' => $mediaUploadWarning,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Property store failed', [
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

        $data = $property->toArray();
        $this->hydratePropertyUserNames([$property], (int) $tenantId);
        $data['captador_nome'] = $property->captador_nome;
        $data['inserido_por_nome'] = $property->inserido_por_nome;
        $authUser = $request->user();
        if ($authUser?->role === 'trainee') {
            unset($data['proprietario_nome'], $data['proprietario_telefone'], $data['proprietario_email'], $data['proprietario_observacoes']);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($data, [
                'portal_tenant_ids' => $this->getPropertyPortalTenantIds($property->id, (int) $tenantId),
            ]),
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
            Log::warning('Property update: No tenant context', [
                'property_id' => $id,
                'ip' => $request->ip(),
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'No tenant context'], 400);
        }

        // ========== BUSCAR IMÓVEL (ISOLADO POR TENANT) ==========
        $property = Property::where('tenant_id', $tenantId)->find($id);

        if (!$property) {
            Log::warning('Property update: Property not found or belongs to different tenant', [
                'property_id' => $id,
                'tenant_id' => $tenantId,
                'user_id' => $this->resolveUserId($request),
            ]);
            return response()->json(['error' => 'Property not found'], 404);
        }

        Log::info('Property update: Tenant validated', [
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
            'finalidade_imovel' => 'nullable|string|in:venda,aluguel,temporada,venda_aluguel,aluguel_temporada',
            'valor_venda' => 'nullable|numeric|min:0',
            'valor_aluguel' => 'nullable|numeric|min:0',
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
            'descricao_resumida' => 'nullable|string|max:1000',
            'caracteristicas' => 'nullable|string',
            'classificacoes' => 'nullable|string',
            'local_chaves' => 'nullable|string|max:255',
            'status_chaves' => 'nullable|string|in:disponivel,retirada,reserva',
            'visibilidade_endereco' => 'nullable|string|in:completo,bairro_cidade,cidade_estado,oculto',
            'portal_tenant_ids' => 'nullable|array',
            'portal_tenant_ids.*' => 'integer|exists:tenants,id',
            'active' => 'nullable|boolean',
            'exibir_imovel' => 'nullable|boolean',
            'destaque' => 'nullable|boolean',
            'exclusividade' => 'nullable|boolean',
            // Upload de mídia
            'media.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,avif,jfif,heic,heif,mp4,mov,m4v,avi,webm,mkv|max:102400', // Max 100MB
            'existing_images' => 'nullable|string', // JSON array de URLs existentes
            'destaque_index' => 'nullable|integer',
            'captador_user_id' => 'nullable|integer',
            'construtora_pessoa_id' => 'nullable|integer',
            // Dados do proprietário (interno)
            'proprietario_nome' => 'nullable|string|max:150',
            'proprietario_telefone' => 'nullable|string|max:30',
            'proprietario_email' => 'nullable|email|max:150',
            'proprietario_observacoes' => 'nullable|string',
        ]);

        $this->appendPropertyBusinessRulesValidation($validator, $request, true);

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
            $data = $this->normalizeStructuredPropertyData($validator->validated());
            [$captadorUserId, $captadorNome] = $this->resolveCaptadorData($tenantId, $data['captador_user_id'] ?? null);
            $data['captador_user_id'] = $captadorUserId;
            $data['captador_nome'] = $captadorNome;
            
            // NUNCA alterar quem inseriu o imóvel
            unset($data['inserido_por_user_id'], $data['inserido_por_nome']);
            
            // ========== VALIDAÇÃO DE PORTAL_TENANT_IDS (APENAS TENANTS ASSOCIADOS) ==========
            // Portal tenant IDs devem incluir apenas tenants associados ao tenant atual
            $portalTenantIds = null;
            if (isset($data['portal_tenant_ids']) && !empty($data['portal_tenant_ids'])) {
                $allowedTenantIds = $this->getAllowedPortalTenantIds($tenantId);
                $requestedIds = is_array($data['portal_tenant_ids']) ? $data['portal_tenant_ids'] : [];
                
                // Filtrar apenas os tenants associados (que não seja o próprio tenant)
                $validIds = array_filter(
                    $requestedIds,
                    fn ($id) => in_array((int)$id, $allowedTenantIds) && (int)$id !== (int)$tenantId
                );
                
                if (!empty($validIds)) {
                    $portalTenantIds = $validIds;
                }
            }
            unset($data['portal_tenant_ids']);
            
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
            
            $mediaUploadWarning = null;

            // Novas imagens
            if ($request->hasFile('media')) {
                try {
                    $uploadedFiles = $this->normalizeUploadedFiles($request->file('media'));
                    if (!empty($uploadedFiles)) {
                        $uploadedUrls = $this->uploadMedia(
                            $uploadedFiles,
                            $tenantId,
                            $property->codigo_imovel
                        );
                        $imagens = array_merge($imagens, $uploadedUrls);
                    }
                } catch (\Throwable $uploadError) {
                    Log::error('Property update: media upload failed, continuing without media', [
                        'tenant_id' => $tenantId,
                        'property_id' => $id,
                        'codigo' => $property->codigo_imovel,
                        'error' => $uploadError->getMessage(),
                        'file' => $uploadError->getFile(),
                        'line' => $uploadError->getLine(),
                    ]);
                    $mediaUploadWarning = 'Imóvel atualizado, mas ocorreu falha no upload da mídia.';
                }
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
                Log::warning('Property update: dropped fields not present in schema', [
                    'tenant_id' => $tenantId,
                    'property_id' => $id,
                    'fields' => $droppedFields,
                ]);
            }

            // ========== ATUALIZAR IMÓVEL ==========
            $property->update($data);
            $selectedPortalTenantIds = $this->syncPropertyPortalTenantIds(
                $property->id,
                (int) $tenantId,
                is_array($portalTenantIds)
                    ? $portalTenantIds
                    : $this->getPropertyPortalTenantIds($property->id, (int) $tenantId)
            );
            
            Log::info('Property updated', [
                'property_id' => $id,
                'tenant_id' => $tenantId,
                'codigo' => $property->codigo_imovel,
                'user_id' => $this->resolveUserId($request),
            ]);
            
            $this->flushPortalCaches($selectedPortalTenantIds);

            return response()->json([
                'success' => true,
                'data' => array_merge($property->fresh()->toArray(), [
                    'portal_tenant_ids' => $selectedPortalTenantIds,
                ]),
                'message' => $mediaUploadWarning ?: "Imóvel {$property->codigo_imovel} atualizado com sucesso!",
                'upload_warning' => $mediaUploadWarning,
            ]);
        } catch (\Throwable $e) {
            Log::error('Property update failed', [
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
        $authUser = $request->user();
        if (!in_array($authUser?->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'error' => 'Apenas administradores podem excluir imóveis'], 403);
        }

        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $property = Property::where('tenant_id', $tenantId)->find($id);

        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $portalTenantIds = $this->getPropertyPortalTenantIds($property->id, (int) $tenantId);
        $this->trashService->moveToTrash(
            $property,
            'admin',
            'manual_delete',
            $authUser?->id,
            ['ip' => $request->ip()]
        );
        $this->flushPortalCaches($portalTenantIds);

        return response()->json([
            'success' => true,
            'message' => 'Imóvel movido para a lixeira com sucesso',
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
                    'valor_aluguel',
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
                'Valor Venda',
                'Valor Aluguel',
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
                    $property->valor_aluguel ?? '',
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
     * Gerar descrição completa + resumida com IA para cadastro/edição de imóvel
     *
     * POST /api/imoveis/ai/gerar-descricao
     */
    public function generateDescriptions(Request $request)
    {
        try {
            $tenantId = $this->resolveTenantId($request);
            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'No tenant context'], 400);
            }

            $validator = Validator::make($request->all(), [
                'tipo_imovel' => 'required|string|max:100',
                'finalidade_imovel' => 'nullable|string|max:50',
                'valor_venda' => 'nullable|numeric|min:0',
                'valor_aluguel' => 'nullable|numeric|min:0',
                'cidade' => 'required|string|max:100',
                'estado' => 'required|string|max:10',
                'bairro' => 'nullable|string|max:100',
                'dormitorios' => 'nullable|integer|min:0',
                'banheiros' => 'nullable|integer|min:0',
                'garagem' => 'nullable|integer|min:0',
                'area_total' => 'nullable|numeric|min:0',
                'descricao_base' => 'nullable|string|max:5000',
                'tom' => 'nullable|string|in:premium,tecnico,acolhedor,objetivo',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $openaiKey = $this->resolveTenantOpenAiKey((int) $tenantId);
            if (!$openaiKey) {
                $fallback = $this->fallbackDescriptions($data);
                return response()->json([
                    'success' => true,
                    'data' => $fallback,
                    'warning' => 'OpenAI nao configurada para este tenant. Retorno em modo fallback.',
                ]);
            }

            $tom = $data['tom'] ?? 'premium';
            $descricaoBase = trim((string) ($data['descricao_base'] ?? ''));
            $location = trim(($data['bairro'] ?? '') . ', ' . ($data['cidade'] ?? '') . '/' . ($data['estado'] ?? ''), ', ');
            $finalidade = $data['finalidade_imovel'] ?? 'venda';
            $valorPrincipal = str_contains($finalidade, 'aluguel') || str_contains($finalidade, 'temporada')
                ? (float) ($data['valor_aluguel'] ?? ($data['valor_venda'] ?? 0))
                : (float) ($data['valor_venda'] ?? ($data['valor_aluguel'] ?? 0));

            $prompt = "Crie duas versões de texto para anúncio de imóvel em português-BR.

Formato de resposta obrigatório:
{
  \"descricao\": \"texto longo com 2 a 4 parágrafos\",
  \"descricao_resumida\": \"texto curto com até 220 caracteres\"
}

Dados do imóvel:
- Tipo: {$data['tipo_imovel']}
- Finalidade: {$finalidade}
- Localização: {$location}
- Valor: R$ " . number_format($valorPrincipal, 2, ',', '.') . "
- Dormitórios: " . ((int) ($data['dormitorios'] ?? 0)) . "
- Banheiros: " . ((int) ($data['banheiros'] ?? 0)) . "
- Garagem: " . ((int) ($data['garagem'] ?? 0)) . "
- Área total: " . ($data['area_total'] ?? 0) . "m²
- Tom desejado: {$tom}
" . ($descricaoBase !== '' ? "Descrição base informada pelo usuário:{$descricaoBase}" : '') . "
Regras:
- Não invente informações que não estão nos dados.
- Não use emojis.
- O texto resumido deve ser claro e comercial.";

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $openaiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Você é redator imobiliário. Responda apenas JSON válido.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 700,
                    'response_format' => ['type' => 'json_object'],
                ]),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                Log::warning('generateDescriptions: OpenAI failed, using fallback', [
                    'tenant_id' => $tenantId,
                    'http_code' => $httpCode,
                    'response' => $response,
                ]);
                $fallback = $this->fallbackDescriptions($data);
                return response()->json(['success' => true, 'data' => $fallback, 'warning' => 'IA indisponível, fallback aplicado.']);
            }

            $decoded = json_decode($response, true);
            $content = $decoded['choices'][0]['message']['content'] ?? '';
            $payload = is_string($content) ? json_decode($content, true) : null;

            if (!is_array($payload) || !isset($payload['descricao']) || !isset($payload['descricao_resumida'])) {
                $fallback = $this->fallbackDescriptions($data);
                return response()->json(['success' => true, 'data' => $fallback, 'warning' => 'Resposta da IA inválida, fallback aplicado.']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'descricao' => trim((string) $payload['descricao']),
                    'descricao_resumida' => substr(trim((string) $payload['descricao_resumida']), 0, 220),
                    'fallback' => false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('generateDescriptions error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar descrições com IA',
            ], 500);
        }
    }

    /**
     * Listagem de chaves por imóvel (admin interno).
     * GET /api/chaves
     */
    public function keysIndex(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No tenant context'], 400);
        }

        $table = (new Property())->getTable();
        if (!Schema::hasTable($table)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $columns = Schema::getColumnListing($table);
        $select = ['id', 'codigo_imovel', 'titulo', 'bairro', 'cidade', 'updated_at'];
        if (in_array('local_chaves', $columns, true)) {
            $select[] = 'local_chaves';
        }
        if (in_array('status_chaves', $columns, true)) {
            $select[] = 'status_chaves';
        }

        $search = trim((string) $request->query('q', ''));

        $query = Property::where('tenant_id', $tenantId)
            ->select($select)
            ->orderBy('updated_at', 'desc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo_imovel', 'like', "%{$search}%")
                    ->orWhere('titulo', 'like', "%{$search}%")
                    ->orWhere('bairro', 'like', "%{$search}%")
                    ->orWhere('cidade', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(300)->get(),
        ]);
    }

    /**
     * Histórico de movimentações de chaves.
     * GET /api/chaves/movimentacoes
     */
    public function keysMovements(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No tenant context'], 400);
        }

        if (!Schema::hasTable('controle_chaves_movimentacoes')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $propertyId = $request->query('property_id');

        $query = DB::table('controle_chaves_movimentacoes as m')
            ->leftJoin('imo_properties as p', 'p.id', '=', 'm.property_id')
            ->where('m.tenant_id', $tenantId)
            ->select([
                'm.id',
                'm.property_id',
                'm.tipo',
                'm.responsavel',
                'm.destino',
                'm.observacoes',
                'm.movimentado_em',
                'm.user_id',
                'p.codigo_imovel',
                'p.titulo',
            ])
            ->orderBy('m.movimentado_em', 'desc');

        if ($propertyId) {
            $query->where('m.property_id', $propertyId);
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit(500)->get(),
        ]);
    }

    /**
     * Registrar retirada/devolução de chave e atualizar status do imóvel.
     * POST /api/chaves/movimentacoes
     */
    public function keysMove(Request $request)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'No tenant context'], 400);
        }

        if (!Schema::hasTable('controle_chaves_movimentacoes')) {
            return response()->json([
                'success' => false,
                'error' => 'Tabela de movimentações de chaves não encontrada. Execute as migrations.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|integer',
            'tipo' => 'required|string|in:retirada,devolucao',
            'responsavel' => 'required|string|max:150',
            'destino' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string|max:2000',
            'local_chaves' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $property = Property::where('tenant_id', $tenantId)->find($data['property_id']);
        if (!$property) {
            return response()->json(['success' => false, 'error' => 'Imóvel não encontrado'], 404);
        }

        DB::table('controle_chaves_movimentacoes')->insert([
            'tenant_id' => $tenantId,
            'property_id' => $property->id,
            'tipo' => $data['tipo'],
            'responsavel' => $data['responsavel'],
            'destino' => $data['destino'] ?? null,
            'observacoes' => $data['observacoes'] ?? null,
            'movimentado_em' => Carbon::now(),
            'user_id' => $this->resolveUserId($request),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $propertyColumns = Schema::getColumnListing((new Property())->getTable());
        if (in_array('status_chaves', $propertyColumns, true)) {
            $newStatus = $data['tipo'] === 'retirada' ? 'retirada' : 'disponivel';
            $property->status_chaves = $newStatus;
        }
        if (in_array('local_chaves', $propertyColumns, true) && array_key_exists('local_chaves', $data) && $data['local_chaves'] !== null) {
            $property->local_chaves = $data['local_chaves'];
        }
        $property->save();

        return response()->json([
            'success' => true,
            'message' => 'Movimentação registrada com sucesso',
            'data' => [
                'property_id' => $property->id,
                'status_chaves' => $property->status_chaves,
                'local_chaves' => $property->local_chaves,
            ],
        ]);
    }

    /**
     * Gerar descrição de propaganda com IA para um imóvel
     * 
     * POST /api/properties/{id}/generate-ad-description
     */
    public function generateAdDescription(Request $request, $id)
    {
        try {
            $tenantId = $this->resolveTenantId($request);
            
            if (!$tenantId) {
                return response()->json(['error' => 'No tenant context'], 400);
            }
            
            $property = Property::where('tenant_id', $tenantId)
                ->findOrFail($id);

            $tenant = Tenant::find($tenantId);
            $openaiKey = null;

            if ($tenant) {
                // Usa a hierarquia padrão do projeto:
                // banco do tenant -> metadata -> ENV específico do tenant -> ENV global.
                $openaiKey = $tenant->getIntegrationValue('openai_api_key');

                // Compatibilidade com instalações antigas que usavam api_key_openai.
                if (!$openaiKey) {
                    $openaiKey = $tenant->getIntegrationValue('api_key_openai');
                }
            }

            if (!$openaiKey) {
                $tenantConfig = DB::table('tenant_configs')
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($tenantConfig) {
                    $rawSettings = $tenantConfig->settings ?? null;
                    if (!empty($rawSettings)) {
                        $settings = json_decode($rawSettings, true);
                        $openaiKey = $settings['openai_api_key'] ?? null;
                    }

                    if (!$openaiKey && !empty($tenantConfig->openai_api_key ?? null)) {
                        $openaiKey = $tenantConfig->openai_api_key;
                    }
                }
            }

            if (!$openaiKey) {
                Log::warning('OpenAI key not configured for ad generation', [
                    'tenant_id' => $tenantId,
                    'tenant_slug' => $tenant?->slug,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Chave da OpenAI não configurada para este tenant'
                ], 400);
            }

            // Preparar informações do imóvel
            $propertyInfo = [
                'title' => $property->titulo ?? $property->type ?? 'Imóvel',
                'type' => $property->type ?? $property->tipo_imovel ?? '',
                'purpose' => $property->finalidade_imovel ?? 'venda',
                'city' => $property->city ?? $property->cidade ?? '',
                'state' => $property->state ?? $property->estado ?? '',
                'neighborhood' => $property->neighborhood ?? $property->bairro ?? '',
                'bedrooms' => $property->bedrooms ?? $property->dormitorios ?? 0,
                'bathrooms' => $property->bathrooms ?? $property->banheiros ?? 0,
                'area' => $property->area ?? $property->area_total ?? 0,
                'price' => (str_contains((string) ($property->finalidade_imovel ?? ''), 'aluguel') || str_contains((string) ($property->finalidade_imovel ?? ''), 'temporada'))
                    ? ($property->valor_aluguel ?? $property->price ?? $property->valor_venda ?? 0)
                    : ($property->price ?? $property->valor_venda ?? $property->valor_aluguel ?? 0),
                'description' => $property->descricao ?? '',
            ];

            // Construir prompt para a IA
            $location = trim("{$propertyInfo['neighborhood']}, {$propertyInfo['city']}/{$propertyInfo['state']}", ', ');
            
            $prompt = "Crie uma descrição CURTA e ATRATIVA para propaganda de imóvel nas redes sociais (Instagram/WhatsApp).

IMPORTANTE: Máximo de 400 caracteres. Use EXATAMENTE a localização informada abaixo.

📍 Localização: {$location}
🏠 Tipo: {$propertyInfo['type']}
🎯 Finalidade: {$propertyInfo['purpose']}
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
                Log::error('OpenAI API Error: ' . $response);
                
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
            Log::error('Error generating ad description: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao gerar descrição: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar imóvel para Imobi Brasil
     * POST /api/imoveis/{id}/enviar-imobi-brasil
     */
    public function enviarImobiBrasil(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        try {
            $property = Property::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $tenant = Tenant::findOrFail($tenantId);

            // Usar o serviço para enviar
            $result = \App\Services\ImobiBrasilService::sendProperty($property, $tenant);

            if ($result['success']) {
                // Enviar imagens automaticamente apenas na PRIMEIRA vez (evita duplicação)
                $property->refresh();
                $imagesResult = null;
                $alreadySentImages = !empty($property->imobi_brasil_images_sent_at);
                if (!$alreadySentImages) {
                    $hasImagesDb = false;
                    try {
                        $hasImagesDb = \App\Models\ImovelImagem::where('codigo', $property->codigo)->exists();
                    } catch (\Exception $dbEx) { /* tabela pode não existir */ }
                    $hasImages = $hasImagesDb || (is_array($property->imagens) && count($property->imagens) > 0);
                    if ($hasImages) {
                        $imagesResult = \App\Services\ImobiBrasilService::sendPropertyImages($property, $tenant);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'external_id' => $result['external_id'],
                    'images_sent' => $imagesResult['images_sent'] ?? 0,
                    'images_total' => $imagesResult['images_total'] ?? 0,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'status' => $result['status'] ?? 500,
                ], $result['status'] ?? 500);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Imóvel não encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar imóvel para Imobi Brasil', [
                'property_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Atualizar imóvel no Imobi Brasil
     * PUT /api/imoveis/{id}/atualizar-imobi-brasil
     */
    public function atualizarImobiBrasil(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        try {
            $property = Property::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $tenant = Tenant::findOrFail($tenantId);

            // Usar o serviço para atualizar
            $result = \App\Services\ImobiBrasilService::updateProperty($property, $tenant);

            if ($result['success']) {
                $property->refresh();

                // Re-sincronizar imagens: excluir as antigas no Imobi Brasil e reenviar todas as atuais
                $imagesResult = ['success' => true, 'images_sent' => 0, 'images_total' => 0, 'errors' => []];
                $codigoImovel = $property->imobi_brasil_external_id;

                if ($codigoImovel) {
                    // 1. Listar imagens existentes no Imobi Brasil e excluir
                    $listaResult = \App\Services\ImobiBrasilService::listPropertyImages((int) $codigoImovel, $tenant);
                    if (!empty($listaResult['result_set'])) {
                        foreach ($listaResult['result_set'] as $imgItem) {
                            $codigoImagem = $imgItem['codigoImagem'] ?? $imgItem['codigo'] ?? null;
                            if ($codigoImagem) {
                                \App\Services\ImobiBrasilService::deletePropertyImage((int) $codigoImovel, (int) $codigoImagem, $tenant);
                            }
                        }
                    }

                    // 2. Enviar todas as imagens atuais
                    $hasImagesDb = false;
                    try {
                        $hasImagesDb = \App\Models\ImovelImagem::where('codigo', $property->codigo)->exists();
                    } catch (\Exception $dbEx) { /* tabela pode não existir */ }
                    $hasImages = $hasImagesDb || (is_array($property->imagens) && count($property->imagens) > 0);

                    if ($hasImages) {
                        $imagesResult = \App\Services\ImobiBrasilService::sendPropertyImages($property, $tenant);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'images_sent' => $imagesResult['images_sent'] ?? 0,
                    'images_total' => $imagesResult['images_total'] ?? 0,
                    'images_errors' => $imagesResult['errors'] ?? [],
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                ], 400);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Imóvel não encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar imóvel no Imobi Brasil', [
                'property_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obter status de envio para Imobi Brasil
     * GET /api/imoveis/{id}/status-imobi-brasil
     */
    public function statusImobiBrasil(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        try {
            $property = Property::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->select([
                    'id',
                    'imobi_brasil_sent',
                    'imobi_brasil_sent_at',
                    'imobi_brasil_external_id',
                    'imobi_brasil_error',
                    'imobi_brasil_images_sent_at',
                ])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'enviado' => (bool) $property->imobi_brasil_sent,
                    'data_envio' => $property->imobi_brasil_sent_at,
                    'external_id' => $property->imobi_brasil_external_id,
                    'erro' => $property->imobi_brasil_error,
                    'images_sent_at' => $property->imobi_brasil_images_sent_at,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Imóvel não encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar imagens do imóvel no Imobi Brasil (debug)
     * GET /api/imoveis/{id}/listar-imagens-imobi-brasil
     */
    public function listarImagensImobiBrasil(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) return response()->json(['error' => 'No tenant context'], 400);

        $property = Property::where('id', $id)->where('tenant_id', $tenantId)->firstOrFail();
        $tenant   = Tenant::findOrFail($tenantId);

        if (!$property->imobi_brasil_external_id) {
            return response()->json(['error' => 'Imóvel não enviado ao Imobi Brasil'], 400);
        }

        $result = \App\Services\ImobiBrasilService::listPropertyImages(
            (int) $property->imobi_brasil_external_id, $tenant
        );

        return response()->json($result);
    }

    /**
     * Enviar imagens do imóvel para Imobi Brasil
     * POST /api/imoveis/{id}/enviar-imagens-imobi-brasil
     */
    public function enviarImagensImobiBrasil(Request $request, $id)
    {
        $tenantId = $this->resolveTenantId($request);
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        try {
            $property = Property::where('id', $id)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Validar se existem imagens
            $imagensDb = 0;
            try {
                $imagensDb = \App\Models\ImovelImagem::where('codigo', $property->codigo)->count();
            } catch (\Exception $dbEx) { /* tabela pode não existir */ }
            $imagensJson = is_array($property->imagens) ? count($property->imagens) : 0;
            if ($imagensDb === 0 && $imagensJson === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhuma imagem disponível para enviar',
                ], 400);
            }

            // Despachar job em background para evitar timeout do servidor
            SendImobiBrasilImagesJob::dispatch((int) $property->id, (int) $tenantId);

            $total = $imagensDb > 0 ? $imagensDb : $imagensJson;

            return response()->json([
                'success'     => true,
                'message'     => "Envio de {$total} imagens iniciado em background. Aguarde alguns minutos.",
                'images_total' => $total,
                'async'       => true,
            ], 202);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Imóvel não encontrado'], 404);
        } catch (\Exception $e) {
            Log::error('Erro ao disparar job de imagens para Imobi Brasil', [
                'property_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
