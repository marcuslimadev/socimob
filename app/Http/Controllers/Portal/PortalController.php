<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;


use App\Models\Tenant;
use App\Models\Property;
use App\Models\Pessoa;
use App\Models\PropertyEvaluation;
use App\Services\PropertyLikesTablesManager;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PortalController extends Controller
{
    private function sanitizePropertyForPortal($imovel): array
    {
        $data = is_array($imovel) ? $imovel : $imovel->toArray();

        $visibility = $data['visibilidade_endereco'] ?? 'bairro_cidade';
        $bairro = $data['bairro'] ?? null;
        $cidade = $data['cidade'] ?? null;
        $estado = $data['estado'] ?? null;
        $logradouro = $data['logradouro'] ?? null;
        $numero = $data['numero'] ?? null;
        $complemento = $data['complemento'] ?? null;
        $cep = $data['cep'] ?? null;

        if ($visibility === 'oculto') {
            $data['bairro'] = null;
            $data['cidade'] = null;
            $data['estado'] = null;
            $data['logradouro'] = null;
            $data['numero'] = null;
            $data['complemento'] = null;
            $data['cep'] = null;
            $data['endereco_publico'] = 'Localização sob consulta';
        } elseif ($visibility === 'cidade_estado') {
            $data['bairro'] = null;
            $data['logradouro'] = null;
            $data['numero'] = null;
            $data['complemento'] = null;
            $data['cep'] = null;
            $data['endereco_publico'] = trim("{$cidade}, {$estado}", ', ');
        } elseif ($visibility === 'completo') {
            $data['endereco_publico'] = trim("{$logradouro}, {$numero} {$complemento} - {$bairro}, {$cidade}/{$estado}", " ,-");
        } else {
            // bairro_cidade (default seguro)
            $data['logradouro'] = null;
            $data['numero'] = null;
            $data['complemento'] = null;
            $data['cep'] = null;
            $data['endereco_publico'] = trim("{$bairro}, {$cidade}/{$estado}", ', ');
        }

        // Nunca expor metadados internos
        unset(
            $data['tenant_id'],
            $data['api_data'],
            $data['local_chaves'],
            $data['status_chaves']
        );

        return $data;
    }

    /**
     * Retorna configurações do tenant para o portal público
     * GET /api/portal/config
     */
    public function getConfig(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 404);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $config = $tenant->config;
        $portalFinalidades = null;
        if ($config && is_array($config->portal_finalidades)) {
            $portalFinalidades = $config->portal_finalidades;
        }

        // Usar WhatsApp configurado (campo whatsapp_number)
        $whatsappNumber = $config && $config->whatsapp_number 
            ? $config->whatsapp_number 
            : $tenant->contact_phone;

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $tenant->name,
                'contact_phone' => $whatsappNumber,
                'contact_email' => $tenant->contact_email,
                'domain' => $tenant->domain,
                'slogan' => $tenant->slogan ?? 'Encontre o Imóvel dos Seus Sonhos',
                'primary_color' => $tenant->primary_color ?? '#1e293b',
                'secondary_color' => $tenant->secondary_color ?? '#3b82f6',
                'logo' => $tenant->logo_url,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'mascot_url' => $tenant->mascot_url,
                'font_primary' => $config ? $config->font_primary : null,
                'font_secondary' => $config ? $config->font_secondary : null,
                'font_url' => $config ? $config->font_url : null,
                'portal_finalidades' => $portalFinalidades,
                'creci' => $tenant->creci,
                'about_text' => $tenant->about_text,
                'services' => $tenant->services,
                'social_links' => $tenant->social_links,
                'endereco' => $tenant->endereco,
                'office_hours' => $tenant->office_hours,
            ]
        ]);
    }

    /**
     * Lista imoveis disponiveis do tenant (publico)
     * GET /api/portal/imoveis
     */
    public function getImoveis(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 404);
        }

        $table = (new Property())->getTable();
        if (!Schema::hasTable($table)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'total' => 0,
                'cached' => false,
            ]);
        }

        $columns = Schema::getColumnListing($table);
        $hasTenantId = in_array('tenant_id', $columns, true);
        $hasActive = in_array('active', $columns, true);
        $hasExibir = in_array('exibir_imovel', $columns, true);
        $hasFinalidade = in_array('finalidade_imovel', $columns, true);

        $cacheKey = "portal_imoveis_tenant_{$tenantId}";
        $cacheTtl = Carbon::now()->addMinutes(10);
        
        // Desabilitar cache quando há filtros aplicados
        $hasFilters = $request->hasAny(['localizacao', 'tipo', 'finalidade', 'preco']);
        $useCache = !$request->boolean('fresh') && !$hasFilters;
        
        $cachedPayload = $useCache ? Cache::get($cacheKey) : null;

        try {
            PropertyLikesTablesManager::ensurePropertyLikesTableExists();
        } catch (\Throwable $e) {
            Log::warning('PortalController: nao foi possivel garantir tabela property_likes', [
                'error' => $e->getMessage(),
            ]);
        }
        $tenant = Tenant::find($tenantId);
        $config = $tenant ? $tenant->config : null;
        $allowedFinalidades = $config && is_array($config->portal_finalidades)
            ? array_values(array_filter($config->portal_finalidades))
            : null;
        $normalizedFinalidades = $allowedFinalidades
            ? array_map(fn ($value) => Str::lower($value), $allowedFinalidades)
            : null;

        $sharedPropertyIds = [];
        if (Schema::hasTable('property_portal_tenants')) {
            $sharedPropertyIds = DB::table('property_portal_tenants')
                ->where('tenant_id', $tenantId)
                ->pluck('property_id')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        $imoveisQuery = Property::withoutTenant()->with('fotos')->orderBy('created_at', 'desc');
        if ($hasTenantId) {
            $imoveisQuery->where(function ($query) use ($tenantId, $sharedPropertyIds) {
                $query->where('tenant_id', $tenantId);
                if (!empty($sharedPropertyIds)) {
                    $query->orWhereIn('id', $sharedPropertyIds);
                }
            });
        }

        // Aplicar ordenação
        if ($request->has('ordenar') && $request->ordenar) {
            $sortOrder = $request->ordenar;
            switch ($sortOrder) {
                case 'valor_venda_asc':
                    $imoveisQuery->orderBy('valor_venda', 'asc');
                    break;
                case 'valor_venda_desc':
                    $imoveisQuery->orderBy('valor_venda', 'desc');
                    break;
                case 'created_at_desc':
                default:
                    $imoveisQuery->orderBy('created_at', 'desc');
                    break;
            }
        }
        if ($hasActive) {
            $imoveisQuery->where('active', true);
        }
        if ($hasExibir) {
            $imoveisQuery->where('exibir_imovel', true);
        }

        if ($hasFinalidade && $normalizedFinalidades && count($normalizedFinalidades) > 0) {
            $imoveisQuery->whereIn(DB::raw('LOWER(finalidade_imovel)'), $normalizedFinalidades);
        }

        // Filtrar imóveis com valor mínimo (excluir preços inválidos/muito baixos)
        $imoveisQuery->where('valor_venda', '>=', 30000);

        // Aplicar filtros do frontend
        if ($request->has('localizacao') && $request->localizacao) {
            $localizacao = $request->localizacao;
            $imoveisQuery->where(function ($query) use ($localizacao) {
                $query->where('endereco', 'LIKE', "%{$localizacao}%")
                      ->orWhere('bairro', 'LIKE', "%{$localizacao}%")
                      ->orWhere('cidade', 'LIKE', "%{$localizacao}%")
                      ->orWhere('estado', 'LIKE', "%{$localizacao}%");
            });
        }

        if ($request->has('tipo') && $request->tipo) {
            $tipo = $request->tipo;
            $imoveisQuery->where('tipo_imovel', 'LIKE', "%{$tipo}%");
        }

        if ($request->has('finalidade') && $request->finalidade) {
            $finalidade = $request->finalidade;
            if ($finalidade === 'venda') {
                $imoveisQuery->where('finalidade_imovel', 'LIKE', '%venda%');
            } elseif ($finalidade === 'aluguel') {
                $imoveisQuery->where('finalidade_imovel', 'LIKE', '%aluguel%');
            }
        }

        if ($request->has('preco') && $request->preco) {
            $precoRange = $request->preco;
            if (strpos($precoRange, '-') !== false) {
                [$min, $max] = explode('-', $precoRange);
                $min = (float) $min;
                $max = (float) $max;
                $imoveisQuery->whereBetween('valor_venda', [$min, $max]);
            } elseif (strpos($precoRange, '+') !== false) {
                $min = (float) str_replace('+', '', $precoRange);
                $imoveisQuery->where('valor_venda', '>=', $min);
            }
        }

        $imoveis = $imoveisQuery->get();
        $allowShared = filter_var(env('PORTAL_ALLOW_SHARED_PROPERTIES', false), FILTER_VALIDATE_BOOLEAN);
        if ($imoveis->isEmpty() && $allowShared && $hasTenantId) {
            $sharedQuery = Property::withoutTenant()
                ->with('fotos')
                ->whereNull('tenant_id')
                ->orderBy('created_at', 'desc');

            // Aplicar ordenação para propriedades compartilhadas
            if ($request->has('ordenar') && $request->ordenar) {
                $sortOrder = $request->ordenar;
                switch ($sortOrder) {
                    case 'valor_venda_asc':
                        $sharedQuery->orderBy('valor_venda', 'asc');
                        break;
                    case 'valor_venda_desc':
                        $sharedQuery->orderBy('valor_venda', 'desc');
                        break;
                    case 'created_at_desc':
                    default:
                        $sharedQuery->orderBy('created_at', 'desc');
                        break;
                }
            }
            if ($hasActive) {
                $sharedQuery->where('active', true);
            }
            if ($hasExibir) {
                $sharedQuery->where('exibir_imovel', true);
            }

            if ($hasFinalidade && $normalizedFinalidades && count($normalizedFinalidades) > 0) {
                $sharedQuery->whereIn(DB::raw('LOWER(finalidade_imovel)'), $normalizedFinalidades);
            }

            // Filtrar imóveis com valor mínimo
            $sharedQuery->where('valor_venda', '>=', 30000);

            // Aplicar os mesmos filtros do frontend para propriedades compartilhadas
            if ($request->has('localizacao') && $request->localizacao) {
                $localizacao = $request->localizacao;
                $sharedQuery->where(function ($query) use ($localizacao) {
                    $query->where('endereco', 'LIKE', "%{$localizacao}%")
                          ->orWhere('bairro', 'LIKE', "%{$localizacao}%")
                          ->orWhere('cidade', 'LIKE', "%{$localizacao}%")
                          ->orWhere('estado', 'LIKE', "%{$localizacao}%");
                });
            }

            if ($request->has('tipo') && $request->tipo) {
                $tipo = $request->tipo;
                $sharedQuery->where('tipo_imovel', 'LIKE', "%{$tipo}%");
            }

            if ($request->has('finalidade') && $request->finalidade) {
                $finalidade = $request->finalidade;
                if ($finalidade === 'venda') {
                    $sharedQuery->where('finalidade_imovel', 'LIKE', '%venda%');
                } elseif ($finalidade === 'aluguel') {
                    $sharedQuery->where('finalidade_imovel', 'LIKE', '%aluguel%');
                }
            }

            if ($request->has('preco') && $request->preco) {
                $precoRange = $request->preco;
                if (strpos($precoRange, '-') !== false) {
                    [$min, $max] = explode('-', $precoRange);
                    $min = (float) $min;
                    $max = (float) $max;
                    $sharedQuery->whereBetween('valor_venda', [$min, $max]);
                } elseif (strpos($precoRange, '+') !== false) {
                    $min = (float) str_replace('+', '', $precoRange);
                    $sharedQuery->where('valor_venda', '>=', $min);
                }
            }

            $imoveis = $sharedQuery->get();
        }

        $likesMap = collect();
        if (Schema::hasTable('property_likes')) {
            $likesMap = DB::table('property_likes')
                ->select('property_id', DB::raw('COUNT(*) as total'))
                ->where('tenant_id', $tenantId)
                ->groupBy('property_id')
                ->pluck('total', 'property_id');
        }

        $imoveis = $imoveis->map(function ($imovel) use ($likesMap) {
            $imovel->likes_count = (int) ($likesMap[$imovel->id] ?? 0);
            return $this->sanitizePropertyForPortal($imovel);
        });

        $payload = $imoveis->values()->toArray();
        $fromCache = false;

        if (empty($payload) && !empty($cachedPayload)) {
            $payload = $cachedPayload;
            $fromCache = true;
        } elseif (!empty($payload) && $useCache) {
            Cache::put($cacheKey, $payload, $cacheTtl);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
            'total' => count($payload),
            'cached' => $fromCache
        ]);
    }

    /**
     * Detalhes de um imovel especifico (publico)
     * GET /api/portal/imoveis/{id}
     */
    public function getImovel(Request $request, $id)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant não identificado'], 404);
        }

        $table = (new Property())->getTable();
        if (!Schema::hasTable($table)) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $columns = Schema::getColumnListing($table);
        $hasTenantId = in_array('tenant_id', $columns, true);
        $hasActive = in_array('active', $columns, true);
        $hasExibir = in_array('exibir_imovel', $columns, true);
        $hasFinalidade = in_array('finalidade_imovel', $columns, true);

        try {
            PropertyLikesTablesManager::ensurePropertyLikesTableExists();
        } catch (\Throwable $e) {
            Log::warning('PortalController: nao foi possivel garantir tabela property_likes', [
                'error' => $e->getMessage(),
            ]);
        }

        $tenant = Tenant::find($tenantId);
        $config = $tenant ? $tenant->config : null;
        $allowedFinalidades = $config && is_array($config->portal_finalidades)
            ? array_values(array_filter($config->portal_finalidades))
            : null;
        $normalizedFinalidades = $allowedFinalidades
            ? array_map(fn ($value) => Str::lower($value), $allowedFinalidades)
            : null;

        $sharedPropertyIds = [];
        if (Schema::hasTable('property_portal_tenants')) {
            $sharedPropertyIds = DB::table('property_portal_tenants')
                ->where('tenant_id', $tenantId)
                ->pluck('property_id')
                ->map(fn ($value) => (int) $value)
                ->toArray();
        }

        $imovelQuery = Property::withoutTenant()->with('fotos')->where('id', $id);
        if ($hasTenantId) {
            $imovelQuery->where(function ($query) use ($tenantId, $sharedPropertyIds) {
                $query->where('tenant_id', $tenantId);
                if (!empty($sharedPropertyIds)) {
                    $query->orWhereIn('id', $sharedPropertyIds);
                }
            });
        }
        if ($hasActive) {
            $imovelQuery->where('active', true);
        }
        if ($hasExibir) {
            $imovelQuery->where('exibir_imovel', true);
        }
        $imovel = $imovelQuery->first();

        if (!$imovel) {
            return response()->json(['error' => 'Property not found'], 404);
        }
        if ($hasFinalidade && $normalizedFinalidades && count($normalizedFinalidades) > 0) {
            if (!in_array(Str::lower($imovel->finalidade_imovel), $normalizedFinalidades, true)) {
                return response()->json(['error' => 'Property not found'], 404);
            }
        }

        $likesCount = 0;
        if (Schema::hasTable('property_likes')) {
            $likesCount = DB::table('property_likes')
                ->where('tenant_id', $tenantId)
                ->where('property_id', $imovel->id)
                ->count();
        }

        $imovel->likes_count = (int) $likesCount;

        return response()->json([
            'success' => true,
            'data' => $this->sanitizePropertyForPortal($imovel)
        ]);
    }

    /**
     * Registrar interesse/lead (público)
     * POST /api/portal/interesse
     */
    public function registrarInteresse(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        // Criar lead (você pode criar uma tabela 'leads' depois)
        // Por enquanto, retornar sucesso

        return response()->json([
            'success' => true,
            'message' => 'Interesse registrado com sucesso! Entraremos em contato em breve.'
        ]);
    }

    /**
     * Criar lead via chat bot do portal (público, sem auth)
     * POST /api/portal/chat-lead
     */
    public function createChatLead(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 404);
            }

            $emailInput = $request->input('email');
            if (is_string($emailInput)) {
                $emailNormalized = mb_strtolower(trim($emailInput));
                $noEmailTokens = [
                    '',
                    'pular',
                    'nao',
                    'não',
                    'prefiro nao informar',
                    'prefiro não informar',
                    'sem email',
                    'sem e-mail',
                    'nao tenho email',
                    'não tenho email',
                    'nao tenho e-mail',
                    'não tenho e-mail',
                ];

                if (in_array($emailNormalized, $noEmailTokens, true)) {
                    $request->merge(['email' => null]);
                }
            }

            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'whatsapp' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'interesse' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Dados inválidos',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $whatsapp = preg_replace('/\D/', '', $request->whatsapp);

            // Check for existing lead with same whatsapp for this tenant
            $existingLead = \App\Models\Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($whatsapp, $request) {
                    $q->where('telefone', 'like', "%{$whatsapp}%")
                      ->orWhere('whatsapp', 'like', "%{$whatsapp}%");
                    if ($request->email) {
                        $q->orWhere('email', $request->email);
                    }
                })
                ->first();

            if ($existingLead) {
                // Update existing lead
                if ($request->interesse) {
                    $obs = $existingLead->observacoes ?? '';
                    $obs .= ($obs ? "" : '') . "[Chat Portal " . now()->format('d/m/Y H:i') . "] " . $request->interesse;
                    $existingLead->observacoes = $obs;
                }
                if ($request->email && !$existingLead->email) {
                    $existingLead->email = $request->email;
                }
                $existingLead->save();

                $lead = $existingLead;
            } else {
                // Create new lead
                $lead = \App\Models\Lead::create([
                    'tenant_id' => $tenantId,
                    'nome' => $request->nome,
                    'telefone' => $whatsapp,
                    'whatsapp' => $whatsapp,
                    'email' => $request->email,
                    'status' => 'novo',
                    'classificacao' => 'quente',
                    'observacoes' => $request->interesse
                        ? "[Chat Portal " . now()->format('d/m/Y H:i') . "] " . $request->interesse
                        : "[Chat Portal " . now()->format('d/m/Y H:i') . "] Lead capturado via chat automatizado do portal",
                ]);
            }

            // Get tenant WhatsApp number
            $tenant = \App\Models\Tenant::find($tenantId);
            $config = $tenant ? $tenant->config : null;
            $tenantWhatsapp = $config && $config->whatsapp_number
                ? preg_replace('/\D/', '', $config->whatsapp_number)
                : ($tenant ? preg_replace('/\D/', '', $tenant->contact_phone ?? '') : '');

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'whatsapp_number' => $tenantWhatsapp,
                'message' => 'Lead criado com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error('[Portal] Erro ao criar lead via chat', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao registrar seu contato. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Solicitar avaliacao de imovel (publico, sem auth)
     * POST /api/portal/avaliacao
     */
    public function createEvaluationRequest(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 404);
            }

            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:255',
                'telefone' => 'required|string|max:20',
                'email' => 'nullable|email|max:255',
                'tipo_imovel' => 'nullable|string|max:100',
                'endereco' => 'nullable|string|max:500',
                'bairro' => 'nullable|string|max:100',
                'cidade' => 'nullable|string|max:100',
                'observacoes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Dados invalidos',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $telefone = preg_replace('/\D/', '', $request->telefone);

            // Create or find lead
            $existingLead = \App\Models\Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($telefone, $request) {
                    $q->where('telefone', 'like', "%{$telefone}%")
                      ->orWhere('whatsapp', 'like', "%{$telefone}%");
                    if ($request->email) {
                        $q->orWhere('email', $request->email);
                    }
                })
                ->first();

            if ($existingLead) {
                $obs = $existingLead->observacoes ?? '';
                $obs .= ($obs ? "" : '') . "[Avaliacao Portal " . now()->format('d/m/Y H:i') . "] Solicitou avaliacao de imovel";
                if ($request->endereco) $obs .= " - Endereco: {$request->endereco}";
                if ($request->bairro) $obs .= ", {$request->bairro}";
                $existingLead->observacoes = $obs;
                $existingLead->save();
                $lead = $existingLead;
            } else {
                $obsText = "[Avaliacao Portal " . now()->format('d/m/Y H:i') . "] Solicitou avaliacao de imovel";
                if ($request->endereco) $obsText .= " - Endereco: {$request->endereco}";
                if ($request->bairro) $obsText .= ", {$request->bairro}";

                $lead = \App\Models\Lead::create([
                    'tenant_id' => $tenantId,
                    'nome' => $request->nome,
                    'telefone' => $telefone,
                    'whatsapp' => $telefone,
                    'email' => $request->email,
                    'status' => 'novo',
                    'classificacao' => 'quente',
                    'observacoes' => $obsText,
                ]);
            }

            // Create evaluation request
            if (Schema::hasTable('property_evaluations')) {
                PropertyEvaluation::create([
                    'tenant_id' => $tenantId,
                    'nome' => $request->nome,
                    'telefone' => $telefone,
                    'email' => $request->email,
                    'tipo_imovel' => $request->tipo_imovel,
                    'endereco' => $request->endereco,
                    'bairro' => $request->bairro,
                    'cidade' => $request->cidade,
                    'observacoes' => $request->observacoes,
                    'status' => 'pendente',
                    'lead_id' => $lead->id,
                ]);
            }

            // Get tenant WhatsApp number
            $tenant = Tenant::find($tenantId);
            $config = $tenant ? $tenant->config : null;
            $tenantWhatsapp = $config && $config->whatsapp_number
                ? preg_replace('/\D/', '', $config->whatsapp_number)
                : ($tenant ? preg_replace('/\D/', '', $tenant->contact_phone ?? '') : '');

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'whatsapp_number' => $tenantWhatsapp,
                'message' => 'Solicitacao de avaliacao registrada com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error('[Portal] Erro ao criar solicitacao de avaliacao', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro ao registrar sua solicitacao. Tente novamente.',
            ], 500);
        }
    }

    /**
     * Lookup de pessoa por CPF para preencher automaticamente o formulário.
     * GET /api/portal/lookup-cpf?cpf=xxx
     */
    public function lookupCpf(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 404);
        }

        $cpf = preg_replace('/\D/', '', $request->query('cpf', ''));

        if (strlen($cpf) < 11) {
            return response()->json(['success' => false, 'error' => 'CPF inválido'], 422);
        }

        $pessoa = Pessoa::where('tenant_id', $tenantId)
            ->where('cpf', $cpf)
            ->select(['nome', 'email', 'telefone', 'celular', 'cidade', 'bairro', 'cep'])
            ->first();

        if (!$pessoa) {
            return response()->json(['success' => true, 'found' => false, 'message' => 'Nenhum cadastro encontrado para este CPF.']);
        }

        return response()->json([
            'success' => true,
            'found'   => true,
            'data'    => [
                'nome'     => $pessoa->nome,
                'email'    => $pessoa->email,
                'telefone' => $pessoa->celular ?: $pessoa->telefone,
                'cidade'   => $pessoa->cidade,
                'bairro'   => $pessoa->bairro,
                'cep'      => $pessoa->cep,
            ],
        ]);
    }

    public function solicitarVenda(Request $request)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');

            if (!$tenantId) {
                return response()->json(['success' => false, 'error' => 'Tenant não identificado'], 404);
            }

            $validator = Validator::make($request->all(), [
                'tipo_imovel'       => 'required|string|max:80',
                'finalidade'        => 'required|in:venda,aluguel,venda_aluguel',
                'cep'               => 'nullable|string|max:9',
                'cidade'            => 'required|string|max:100',
                'bairro'            => 'nullable|string|max:100',
                'area'              => 'nullable|numeric|min:0',
                'dormitorios'       => 'nullable|integer|min:0|max:20',
                'valor_pretendido'  => 'nullable|numeric|min:0',
                'nome_contato'      => 'required|string|max:160',
                'telefone_contato'  => 'required|string|max:20',
                'email_contato'     => 'nullable|email|max:255',
                'observacoes'       => 'nullable|string|max:2000',
                'photos'            => 'nullable|array|max:20',
                'photos.*'          => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:15360',
                'videos'            => 'nullable|array|max:5',
                'videos.*'          => 'nullable|file|mimes:mp4,mov,avi,wmv,mkv,webm|max:204800',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Dados inválidos',
                    'messages' => $validator->errors(),
                ], 422);
            }

            $telefone = preg_replace('/\D/', '', $request->telefone_contato);
            $now = now()->format('d/m/Y H:i');

            // Criar imóvel inativo em análise
            $valorPretendido = $request->valor_pretendido ? (float) $request->valor_pretendido : 0;
            $obsProperty = "[Solicitação Portal {$now}] Enviado pelo próprio proprietário via portal para análise.";
            if ($request->observacoes) {
                $obsProperty .= ' ' . $request->observacoes;
            }

            $property = Property::create([
                'tenant_id'                => $tenantId,
                'titulo'                   => 'Solicitação: ' . ucfirst($request->tipo_imovel) . ' em ' . $request->cidade,
                'tipo_imovel'              => $request->tipo_imovel,
                'finalidade_imovel'        => $request->finalidade,
                'cep'                      => $request->cep,
                'cidade'                   => $request->cidade,
                'bairro'                   => $request->bairro,
                'area_total'               => $request->area ? (float) $request->area : null,
                'dormitorios'              => $request->dormitorios ? (int) $request->dormitorios : null,
                'valor_venda'              => $valorPretendido,
                'active'                   => false,
                'exibir_imovel'            => false,
                'proprietario_nome'        => $request->nome_contato,
                'proprietario_telefone'    => $telefone,
                'proprietario_email'       => $request->email_contato,
                'proprietario_observacoes' => $obsProperty,
            ]);

            // Armazenar fotos enviadas
            if ($request->hasFile('photos')) {
                $imageUrls = [];
                foreach ((array) $request->file('photos') as $photo) {
                    if (!$photo || !$photo->isValid()) continue;
                    $ext = strtolower($photo->getClientOriginalExtension());
                    $filename = 'img_' . uniqid() . '.' . $ext;
                    $stored = $photo->storeAs(
                        "properties/{$tenantId}/requests/{$property->id}",
                        $filename,
                        'public'
                    );
                    if ($stored) {
                        $imageUrls[] = Storage::disk('public')->url($stored);
                    }
                }
                if (!empty($imageUrls)) {
                    $property->imagens = $imageUrls;
                    $property->save();
                }
            }

            // Armazenar vídeos enviados
            if ($request->hasFile('videos')) {
                $videoNames = [];
                foreach ((array) $request->file('videos') as $video) {
                    if (!$video || !$video->isValid()) continue;
                    $ext = strtolower($video->getClientOriginalExtension());
                    $filename = 'vid_' . uniqid() . '.' . $ext;
                    $video->storeAs(
                        "properties/{$tenantId}/requests/{$property->id}/videos",
                        $filename,
                        'public'
                    );
                    $videoNames[] = htmlspecialchars($video->getClientOriginalName(), ENT_QUOTES, 'UTF-8');
                }
                if (!empty($videoNames)) {
                    $property->proprietario_observacoes .= ' [Vídeos: ' . implode(', ', $videoNames) . ']';
                    $property->save();
                }
            }

            // Criar ou atualizar lead no CRM
            $lead = \App\Models\Lead::where('tenant_id', $tenantId)
                ->where(function ($q) use ($telefone, $request) {
                    $q->where('telefone', 'like', "%{$telefone}%")
                      ->orWhere('whatsapp', 'like', "%{$telefone}%");
                    if ($request->email_contato) {
                        $q->orWhere('email', $request->email_contato);
                    }
                })
                ->first();

            $obsLead = "[Venda Portal {$now}] Solicitou cadastro de imóvel para venda/aluguel: {$request->tipo_imovel} em {$request->cidade}";
            if ($request->valor_pretendido) {
                $obsLead .= ' - Valor pretendido: R$ ' . number_format((float)$request->valor_pretendido, 0, ',', '.');
            }

            if ($lead) {
                $lead->observacoes = ($lead->observacoes ? $lead->observacoes . '' : '') . $obsLead;
                $lead->save();
            } else {
                \App\Models\Lead::create([
                    'tenant_id'    => $tenantId,
                    'nome'         => $request->nome_contato,
                    'telefone'     => $telefone,
                    'whatsapp'     => $telefone,
                    'email'        => $request->email_contato,
                    'status'       => 'novo',
                    'classificacao' => 'quente',
                    'observacoes'  => $obsLead,
                ]);
            }

            return response()->json([
                'success'    => true,
                'property_id' => $property->id,
                'message'    => 'Seu imóvel foi enviado para análise! Nossa equipe entrará em contato em breve.',
            ]);

        } catch (\Exception $e) {
            Log::error('[Portal] Erro em solicitarVenda', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error'   => 'Erro ao registrar sua solicitação. Tente novamente.',
            ], 500);
        }
    }
}
