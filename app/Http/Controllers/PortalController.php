<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PortalController extends Controller
{
    /**
     * Configuração do tenant para o portal
     */
    public function config(Request $request)
    {
        try {
            // Buscar tenant do container (já resolvido pelo middleware ResolveTenant)
            $tenant = app('tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Validar disponibilidade de assets
            $config = $tenant->getPublicConfig();

            // Verificar se logo existe, senão usar padrão
            if ($config['logo_url'] && $config['logo_url'] !== '/images/logo.png') {
                $logoPath = public_path($config['logo_url']);
                if (!file_exists($logoPath)) {
                    Log::warning('Logo not found for tenant', [
                        'tenant_id' => $tenant->id,
                        'logo_url' => $config['logo_url']
                    ]);
                    $config['logo_url'] = '/images/logo.png';
                }
            }

            // Verificar se favicon existe, senão usar padrão
            if ($config['favicon_url'] && $config['favicon_url'] !== '/favicon.ico') {
                $faviconPath = public_path($config['favicon_url']);
                if (!file_exists($faviconPath)) {
                    Log::warning('Favicon not found for tenant', [
                        'tenant_id' => $tenant->id,
                        'favicon_url' => $config['favicon_url']
                    ]);
                    $config['favicon_url'] = '/favicon.ico';
                }
            }

            return response()->json([
                'success' => true,
                'tenant' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading portal config', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar configurações',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar imóveis públicos
     */
    public function imoveis(Request $request)
    {
        try {
            $tenant = app('tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Buscar imóveis do banco de dados
            $query = DB::table('imo_properties')
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->where('exibir_imovel', true)
                ->orderBy('created_at', 'desc');

            // Aplicar filtros
            if ($request->has('tipo') && $request->tipo) {
                $query->where('tipo_imovel', $request->tipo);
            }

            if ($request->has('finalidade') && $request->finalidade) {
                $query->where('finalidade_imovel', $request->finalidade);
            }

            // Filtros adicionais
            if ($request->has('cidade') && $request->cidade) {
                $query->where('cidade', 'like', '%' . $request->cidade . '%');
            }

            if ($request->has('bairro') && $request->bairro) {
                $query->where('bairro', 'like', '%' . $request->bairro . '%');
            }

            if ($request->has('preco_min') && $request->preco_min) {
                $query->where('valor_venda', '>=', $request->preco_min);
            }

            if ($request->has('preco_max') && $request->preco_max) {
                $query->where('valor_venda', '<=', $request->preco_max);
            }

            if ($request->has('dormitorios') && $request->dormitorios) {
                $query->where('dormitorios', '>=', $request->dormitorios);
            }

            // Paginação
            $perPage = $request->get('per_page', 12);
            $page = $request->get('page', 1);

            $total = $query->count();
            $imoveis = $query
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Formatar dados
            $data = $imoveis->map(function($imovel) {
                // Buscar primeira imagem
                $primeiraImagem = null;
                if ($imovel->codigo) {
                    $foto = DB::table('imovel_imagens')
                        ->where('codigo', $imovel->codigo)
                        ->orderBy('ordem', 'asc')
                        ->first();

                    if ($foto) {
                        $primeiraImagem = $foto->url ?? $foto->imagem ?? null;
                    }
                }

                // Se não tiver imagem na tabela, tentar do campo JSON
                if (!$primeiraImagem && !empty($imovel->imagens)) {
                    $imagensJson = is_string($imovel->imagens) ? json_decode($imovel->imagens, true) : $imovel->imagens;
                    if (is_array($imagensJson) && count($imagensJson) > 0) {
                        $primeiraImagem = is_string($imagensJson[0]) ? $imagensJson[0] : ($imagensJson[0]['url'] ?? null);
                    }
                }

                return [
                    'id' => $imovel->id,
                    'codigo' => $imovel->codigo,
                    'titulo' => $imovel->titulo,
                    'tipo' => $imovel->tipo_imovel,
                    'finalidade' => $imovel->finalidade_imovel,
                    'preco' => $imovel->valor_venda,
                    'area' => $imovel->area_total,
                    'quartos' => $imovel->dormitorios,
                    'banheiros' => $imovel->banheiros,
                    'vagas' => $imovel->garagem,
                    'endereco' => trim(implode(', ', array_filter([
                        $imovel->bairro,
                        $imovel->cidade,
                        $imovel->estado
                    ]))),
                    'status' => 'disponivel',
                    'descricao' => mb_substr($imovel->descricao ?? '', 0, 150) . (mb_strlen($imovel->descricao ?? '') > 150 ? '...' : ''),
                    'imagem_principal' => $primeiraImagem,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar imóveis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar imóveis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalhes de um imóvel específico
     */
    public function imovel(Request $request, $id)
    {
        try {
            $tenant = app('tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Buscar imóvel com fotos relacionadas
            $imovel = DB::table('imo_properties')
                ->where('id', $id)
                ->where('tenant_id', $tenant->id)
                ->where('active', true)
                ->where('exibir_imovel', true)
                ->first();

            if (!$imovel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imóvel não encontrado ou indisponível'
                ], 404);
            }

            // Buscar imagens do imóvel
            $fotos = [];
            if ($imovel->codigo) {
                $fotosDb = DB::table('imovel_imagens')
                    ->where('codigo', $imovel->codigo)
                    ->orderBy('ordem', 'asc')
                    ->get();

                $fotos = $fotosDb->map(function($foto) {
                    return [
                        'id' => $foto->id,
                        'url' => $foto->url ?? $foto->imagem,
                        'descricao' => $foto->descricao ?? '',
                        'ordem' => $foto->ordem ?? 0,
                    ];
                })->toArray();
            }

            // Se tiver imagens no campo JSON, adicionar também
            if (!empty($imovel->imagens)) {
                $imagensJson = is_string($imovel->imagens) ? json_decode($imovel->imagens, true) : $imovel->imagens;
                if (is_array($imagensJson)) {
                    foreach ($imagensJson as $img) {
                        if (is_string($img)) {
                            $fotos[] = ['url' => $img, 'descricao' => '', 'ordem' => 999];
                        } elseif (is_array($img) && isset($img['url'])) {
                            $fotos[] = $img;
                        }
                    }
                }
            }

            // Formatar dados para o frontend
            $data = [
                'id' => $imovel->id,
                'codigo' => $imovel->codigo,
                'titulo' => $imovel->titulo,
                'descricao' => $imovel->descricao,
                'tipo' => $imovel->tipo_imovel,
                'finalidade' => $imovel->finalidade_imovel,
                'preco' => $imovel->valor_venda,
                'area' => $imovel->area_total,
                'quartos' => $imovel->dormitorios,
                'banheiros' => $imovel->banheiros,
                'vagas' => $imovel->garagem,
                'endereco' => [
                    'logradouro' => $imovel->logradouro,
                    'bairro' => $imovel->bairro,
                    'cidade' => $imovel->cidade,
                    'estado' => $imovel->estado,
                ],
                'endereco_completo' => trim(implode(', ', array_filter([
                    $imovel->logradouro,
                    $imovel->bairro,
                    $imovel->cidade,
                    $imovel->estado
                ]))),
                'status' => 'disponivel',
                'fotos' => $fotos,
                'videos' => [], // TODO: Adicionar suporte a vídeos se houver campo na tabela
                'caracteristicas' => [
                    'dormitorios' => $imovel->dormitorios,
                    'banheiros' => $imovel->banheiros,
                    'garagem' => $imovel->garagem,
                    'area_total' => $imovel->area_total,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching property details', [
                'property_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar imóvel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login específico do portal
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
            }

            // Buscar usuário
            $user = DB::table('users')
                ->where('email', $request->email)
                ->first();

            if (!$user || !password_verify($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas'
                ], 401);
            }

            // Gerar token (mesmo formato do AuthController)
            $secret = env('APP_KEY', 'base64:MjlDaTFXTEZ6WDZrVXBJQk01bE9WMDNQU3JIY2dETjQ=');
            $token = base64_encode($user->id . '|' . time() . '|' . $secret);

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tipo' => $user->tipo ?? 'Cliente'
                ],
                'message' => 'Login realizado com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao fazer login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar imóveis disponíveis para clientes
     * (Alias para método imoveis, mantido por compatibilidade)
     */
    public function properties(Request $request)
    {
        // Redirecionar para o método imoveis que já tem a implementação completa
        return $this->imoveis($request);
    }

    /**
     * Registrar interesse de cliente em imóvel
     */
    public function registrarInteresse(Request $request)
    {
        try {
            $tenant = app('tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não encontrado'
                ], 404);
            }

            // Validação robusta de dados
            $validator = Validator::make($request->all(), [
                'property_id' => 'required|integer|exists:imo_properties,id',
                'mensagem' => 'nullable|string|max:1000',
                'nome' => 'required_without:user_id|string|max:255',
                'email' => 'required_without:user_id|email|max:255',
                'telefone' => 'required_without:user_id|string|max:20',
            ], [
                'property_id.required' => 'O imóvel é obrigatório',
                'property_id.exists' => 'Imóvel não encontrado',
                'mensagem.max' => 'A mensagem não pode ter mais de 1000 caracteres',
                'nome.required_without' => 'O nome é obrigatório',
                'email.required_without' => 'O email é obrigatório',
                'email.email' => 'Email inválido',
                'telefone.required_without' => 'O telefone é obrigatório',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados inválidos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $propertyId = $request->input('property_id');
            $mensagem = $request->input('mensagem');

            // Buscar imóvel
            $property = DB::table('imo_properties')
                ->where('id', $propertyId)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$property) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imóvel não encontrado'
                ], 404);
            }

            // Buscar ou criar lead
            $leadData = [
                'tenant_id' => $tenant->id,
                'nome' => $user ? $user->name : $request->input('nome'),
                'email' => $user ? $user->email : $request->input('email'),
                'telefone' => $user ? ($user->telefone ?? $request->input('telefone')) : $request->input('telefone'),
                'status' => 'novo',
                'observacoes' => $mensagem ? "Interesse pelo imóvel #{$property->codigo}: {$mensagem}" : "Interesse pelo imóvel #{$property->codigo}",
                'primeira_interacao' => now(),
                'ultima_interacao' => now(),
            ];

            $lead = DB::table('leads')
                ->where('tenant_id', $tenant->id)
                ->where('email', $leadData['email'])
                ->first();

            if ($lead) {
                // Atualizar lead existente
                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update([
                        'observacoes' => ($lead->observacoes ? $lead->observacoes . "\n\n" : '') . $leadData['observacoes'],
                        'ultima_interacao' => now(),
                    ]);
                $leadId = $lead->id;
            } else {
                // Criar novo lead
                $leadId = DB::table('leads')->insertGetId($leadData);
            }

            // Calcular score de probabilidade de conversão
            $matchScore = $this->calculateMatchScore($lead ?? (object)$leadData, $property);

            // Verificar se já existe interesse registrado
            $existingMatch = DB::table('lead_property_matches')
                ->where('lead_id', $leadId)
                ->where('property_id', $propertyId)
                ->first();

            if ($existingMatch) {
                // Atualizar score
                DB::table('lead_property_matches')
                    ->where('id', $existingMatch->id)
                    ->update([
                        'match_score' => $matchScore,
                        'updated_at' => now(),
                    ]);
            } else {
                // Criar novo registro de interesse
                DB::table('lead_property_matches')->insert([
                    'tenant_id' => $tenant->id,
                    'lead_id' => $leadId,
                    'property_id' => $propertyId,
                    'match_score' => $matchScore,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Registrar atividade para histórico
            DB::table('atividades')->insert([
                'tenant_id' => $tenant->id,
                'lead_id' => $leadId,
                'tipo' => 'interesse_imovel',
                'descricao' => "Cliente demonstrou interesse no imóvel {$property->titulo} (Código: {$property->codigo}). Score: {$matchScore}%",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Notificar corretor responsável ou admin
            $corretor = null;
            if (isset($lead->corretor_id) && $lead->corretor_id) {
                $corretor = DB::table('users')->where('id', $lead->corretor_id)->first();
            }

            if (!$corretor) {
                // Buscar qualquer corretor do tenant
                $corretor = DB::table('users')
                    ->where('tenant_id', $tenant->id)
                    ->where('role', 'corretor')
                    ->where('is_active', true)
                    ->first();
            }

            if (!$corretor) {
                // Se não houver corretor, buscar admin
                $corretor = DB::table('users')
                    ->where('tenant_id', $tenant->id)
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->first();
            }

            if ($corretor) {
                // Criar notificação in-app
                DB::table('notifications')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $corretor->id,
                    'property_id' => $propertyId,
                    'type' => 'property_interest',
                    'title' => 'Novo Interesse em Imóvel',
                    'message' => "Cliente {$leadData['nome']} demonstrou interesse no imóvel {$property->titulo}. Score de conversão: {$matchScore}%",
                    'action_url' => "/leads/{$leadId}",
                    'channel' => 'in_app',
                    'data' => json_encode([
                        'lead_id' => $leadId,
                        'property_id' => $propertyId,
                        'match_score' => $matchScore,
                    ]),
                    'is_read' => false,
                    'is_sent' => true,
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // TODO: Enviar email ao corretor
                // TODO: Enviar WhatsApp ao corretor (se configurado)
            }

            // Buscar sugestões baseadas no interesse
            $sugestoes = $this->getSuggestedProperties($property, $tenant->id, $propertyId);

            Log::info('Cliente demonstrou interesse', [
                'lead_id' => $leadId,
                'client_email' => $leadData['email'],
                'property_id' => $propertyId,
                'match_score' => $matchScore,
                'corretor_notificado' => $corretor ? $corretor->id : null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interesse registrado com sucesso! Em breve um corretor entrará em contato.',
                'data' => [
                    'lead_id' => $leadId,
                    'match_score' => $matchScore,
                    'sugestoes' => $sugestoes,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error registering property interest', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar interesse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular score de correspondência entre lead e imóvel
     *
     * @param object $lead
     * @param object $property
     * @return float
     */
    private function calculateMatchScore($lead, $property): float
    {
        $score = 50; // Base score

        // Score baseado em orçamento
        if (isset($lead->budget_min) && isset($lead->budget_max) && $property->valor_venda) {
            if ($property->valor_venda >= $lead->budget_min && $property->valor_venda <= $lead->budget_max) {
                $score += 30;
            } elseif ($property->valor_venda < $lead->budget_min * 1.2 && $property->valor_venda > $lead->budget_max * 0.8) {
                $score += 15;
            }
        }

        // Score baseado em quartos
        if (isset($lead->quartos) && $property->dormitorios) {
            if ($property->dormitorios >= $lead->quartos) {
                $score += 10;
            } elseif ($property->dormitorios == $lead->quartos - 1) {
                $score += 5;
            }
        }

        // Score baseado em localização
        if (isset($lead->preferencia_bairro) && $property->bairro) {
            $bairrosPreferidos = explode(',', strtolower($lead->preferencia_bairro));
            $bairroImovel = strtolower($property->bairro);

            foreach ($bairrosPreferidos as $bairro) {
                if (str_contains($bairroImovel, trim($bairro))) {
                    $score += 10;
                    break;
                }
            }
        }

        return min(100, max(0, $score));
    }

    /**
     * Buscar imóveis similares para sugestões
     *
     * @param object $property
     * @param int $tenantId
     * @param int $excludeId
     * @return array
     */
    private function getSuggestedProperties($property, $tenantId, $excludeId): array
    {
        // Buscar imóveis similares
        $query = DB::table('imo_properties')
            ->where('tenant_id', $tenantId)
            ->where('active', true)
            ->where('exibir_imovel', true)
            ->where('id', '!=', $excludeId);

        // Mesmo tipo
        if ($property->tipo_imovel) {
            $query->where('tipo_imovel', $property->tipo_imovel);
        }

        // Mesma finalidade
        if ($property->finalidade_imovel) {
            $query->where('finalidade_imovel', $property->finalidade_imovel);
        }

        // Faixa de preço similar (± 20%)
        if ($property->valor_venda) {
            $query->whereBetween('valor_venda', [
                $property->valor_venda * 0.8,
                $property->valor_venda * 1.2
            ]);
        }

        $sugestoes = $query->limit(3)->get();

        return $sugestoes->map(function($imovel) {
            return [
                'id' => $imovel->id,
                'codigo' => $imovel->codigo,
                'titulo' => $imovel->titulo,
                'tipo' => $imovel->tipo_imovel,
                'preco' => $imovel->valor_venda,
                'area' => $imovel->area_total,
                'quartos' => $imovel->dormitorios,
                'endereco' => trim(implode(', ', array_filter([
                    $imovel->bairro,
                    $imovel->cidade
                ]))),
            ];
        })->toArray();
    }
}
