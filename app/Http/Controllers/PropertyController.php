<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\PropertySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
     * Criar novo imóvel
     * POST /api/imoveis
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo_imovel' => 'required|string|max:50|unique:imo_properties,codigo_imovel',
            'referencia_imovel' => 'nullable|string|max:50',
            'tipo_imovel' => 'required|string|max:100',
            'finalidade_imovel' => 'nullable|string|in:venda,aluguel,temporada',
            'valor_venda' => 'nullable|numeric',
            'valor_condominio' => 'nullable|numeric',
            'valor_iptu' => 'nullable|numeric',
            'dormitorios' => 'nullable|integer',
            'suites' => 'nullable|integer',
            'banheiros' => 'nullable|integer',
            'garagem' => 'nullable|integer',
            'area_total' => 'nullable|numeric',
            'area_privativa' => 'nullable|numeric',
            'area_terreno' => 'nullable|numeric',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['active'] = $data['active'] ?? true;
        $data['exibir_imovel'] = $data['exibir_imovel'] ?? true;

        $property = Property::create($data);

        return response()->json([
            'success' => true,
            'data' => $property,
        ], 201);
    }

    /**
     * Atualizar imóvel
     * PUT /api/imoveis/{id}
     */
    public function update(Request $request, $id)
    {
        $property = Property::find($id);

        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo_imovel' => 'nullable|string|max:50|unique:imo_properties,codigo_imovel,' . $id,
            'referencia_imovel' => 'nullable|string|max:50',
            'tipo_imovel' => 'nullable|string|max:100',
            'finalidade_imovel' => 'nullable|string|in:venda,aluguel,temporada',
            'valor_venda' => 'nullable|numeric',
            'valor_condominio' => 'nullable|numeric',
            'valor_iptu' => 'nullable|numeric',
            'dormitorios' => 'nullable|integer',
            'suites' => 'nullable|integer',
            'banheiros' => 'nullable|integer',
            'garagem' => 'nullable|integer',
            'area_total' => 'nullable|numeric',
            'area_privativa' => 'nullable|numeric',
            'area_terreno' => 'nullable|numeric',
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $property->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $property,
        ]);
    }

    /**
     * Exportar imóveis para CSV
     * GET /api/imoveis/export
     */
    public function export()
    {
        try {
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
}
