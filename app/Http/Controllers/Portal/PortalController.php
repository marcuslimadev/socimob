<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Property;
use App\Services\PropertyLikesTablesManager;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PortalController extends Controller
{
    /**
     * Retorna configurações do tenant para o portal público
     * GET /api/portal/config
     */
    public function getConfig(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            // Fallback para desenvolvimento - usar tenant ID 1
            $tenantId = 1;
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

        // Priorizar WhatsApp do Twilio (usado para atendimento) sobre contact_phone
        $whatsappNumber = $config && $config->twilio_whatsapp_from 
            ? $config->twilio_whatsapp_from 
            : $tenant->contact_phone;

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $tenant->name,
                'contact_phone' => $whatsappNumber,
                'contact_email' => $tenant->contact_email,
                'domain' => $tenant->domain,
                'slogan' => $tenant->slogan ?? 'Encontre o Imovel dos Seus Sonhos',
                'primary_color' => $tenant->primary_color ?? '#1e293b',
                'secondary_color' => $tenant->secondary_color ?? '#3b82f6',
                'logo' => $tenant->logo_url,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'portal_finalidades' => $portalFinalidades,
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
            // Fallback para desenvolvimento - usar tenant ID 1
            $tenantId = 1;
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
            \Log::warning('PortalController: nao foi possivel garantir tabela property_likes', [
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

        $imoveisQuery = Property::withoutTenant()->with('fotos')->orderBy('created_at', 'desc');
        if ($hasTenantId) {
            $imoveisQuery->where('tenant_id', $tenantId);
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
        $allowShared = filter_var(env('PORTAL_ALLOW_SHARED_PROPERTIES', true), FILTER_VALIDATE_BOOLEAN);
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
            return $imovel;
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
            $tenantId = 1; // Default for testing
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
            \Log::warning('PortalController: nao foi possivel garantir tabela property_likes', [
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

        $imovelQuery = Property::withoutTenant()->with('fotos')->where('id', $id);
        if ($hasTenantId) {
            $imovelQuery->where('tenant_id', $tenantId);
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
            'data' => $imovel
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
}




