<?php

namespace App\Http\Controllers;

use App\Services\PropertySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    private $syncService;
    
    public function __construct(PropertySyncService $syncService)
    {
        $this->syncService = $syncService;
    }
    
    /**
     * Sincronizar imóveis manualmente
     * 
     * GET /api/properties/sync
     */
    public function sync()
    {
        $result = $this->syncService->syncAll();
        
        if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sincronização concluída com sucesso',
                    'data' => $result['stats'],
                    'time_ms' => $result['time_ms'],
                    'errors_detail' => $result['errors_detail']
                ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
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
    public function detalhesCompletos($codigo)
    {
        try {
            // Buscar imóvel pelo código
            $imovel = DB::table('imo_properties')
                ->where('codigo_imovel', $codigo)
                ->orWhere('referencia_imovel', $codigo)
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
                    'valor_aluguel' => $imovel->valor_aluguel ?? null,
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
}
