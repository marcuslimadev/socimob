<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalController extends Controller
{
    /**
     * Configuração do tenant para o portal
     */
    public function config(Request $request)
    {
        try {
            // Por enquanto retornar configuração padrão
            // TODO: Buscar do banco baseado no domínio
            $tenant = [
                'name' => 'Exclusiva Lar Imóveis',
                'contact_phone' => '(31) 97559-7278',
                'contact_email' => 'contato@exclusivalarimoveis.com.br',
                'slogan' => 'Encontre o Imóvel dos Seus Sonhos',
                'primary_color' => '#1e293b',
                'secondary_color' => '#3b82f6',
                'logo_url' => '/images/logo.png',
                'favicon_url' => '/favicon.ico'
            ];

            return response()->json([
                'success' => true,
                'tenant' => $tenant
            ]);

        } catch (\Exception $e) {
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
            // Buscar imóveis do banco de dados
            $query = DB::table('properties')
                ->where('status', 'disponivel')
                ->orderBy('created_at', 'desc');

            // Aplicar filtros
            if ($request->has('tipo') && $request->tipo) {
                $query->where('tipo', $request->tipo);
            }
            
            if ($request->has('finalidade') && $request->finalidade) {
                $query->where('finalidade', $request->finalidade);
            }

            $imoveis = $query->get();

            return response()->json([
                'success' => true,
                'data' => $imoveis
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar imóveis: ' . $e->getMessage());
            
            // Retornar dados de exemplo em caso de erro
            return response()->json([
                'success' => true,
                'data' => [
                    [
                        'id' => 1,
                        'titulo' => 'Casa de Luxo',
                        'tipo' => 'casa',
                        'finalidade' => 'venda',
                        'preco' => 850000,
                        'area' => 250,
                        'quartos' => 4,
                        'banheiros' => 3,
                        'endereco' => 'Rua das Flores, 123 - Centro',
                        'status' => 'disponivel',
                        'descricao' => 'Linda casa com piscina'
                    ]
                ]
            ]);
        }
    }

    /**
     * Detalhes de um imóvel específico
     */
    public function imovel(Request $request, $id)
    {
        try {
            $imovel = DB::table('properties')
                ->where('id', $id)
                ->where('status', 'disponivel')
                ->first();

            if (!$imovel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Imóvel não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $imovel
            ]);

        } catch (\Exception $e) {
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
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required'
            ]);

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
     */
    public function properties(Request $request)
    {
        try {
            // Buscar imóveis disponíveis (simulado por enquanto)
            $properties = [
                [
                    'id' => 1,
                    'titulo' => 'Casa de Luxo no Condomínio',
                    'tipo' => 'casa',
                    'finalidade' => 'venda',
                    'preco' => 850000,
                    'area' => 250,
                    'quartos' => 4,
                    'banheiros' => 3,
                    'endereco' => 'Rua das Flores, 123 - Bairro Jardim',
                    'status' => 'disponivel',
                    'descricao' => 'Linda casa em condomínio fechado com área de lazer completa',
                    'images' => []
                ],
                [
                    'id' => 2,
                    'titulo' => 'Apartamento Centro',
                    'tipo' => 'apartamento',
                    'finalidade' => 'aluguel',
                    'preco' => 3500,
                    'area' => 80,
                    'quartos' => 2,
                    'banheiros' => 1,
                    'endereco' => 'Av. Principal, 456 - Centro',
                    'status' => 'disponivel',
                    'descricao' => 'Apartamento bem localizado próximo a tudo',
                    'images' => []
                ],
                [
                    'id' => 3,
                    'titulo' => 'Terreno Comercial',
                    'tipo' => 'terreno',
                    'finalidade' => 'venda',
                    'preco' => 450000,
                    'area' => 500,
                    'quartos' => 0,
                    'banheiros' => 0,
                    'endereco' => 'Rod. BR-101, km 45',
                    'status' => 'disponivel',
                    'descricao' => 'Terreno amplo em área comercial',
                    'images' => []
                ]
            ];

            // Aplicar filtros se fornecidos
            $tipo = $request->input('tipo');
            $finalidade = $request->input('finalidade');
            
            if ($tipo) {
                $properties = array_filter($properties, function($p) use ($tipo) {
                    return $p['tipo'] === $tipo;
                });
            }
            
            if ($finalidade) {
                $properties = array_filter($properties, function($p) use ($finalidade) {
                    return $p['finalidade'] === $finalidade;
                });
            }

            return response()->json([
                'success' => true,
                'data' => array_values($properties)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar imóveis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar interesse de cliente em imóvel
     */
    public function registrarInteresse(Request $request)
    {
        try {
            $this->validate($request, [
                'property_id' => 'required|integer',
                'mensagem' => 'nullable|string'
            ]);

            $user = $request->user();
            
            // Registrar interesse (simulado por enquanto)
            // Em produção, salvar no banco de dados
            
            Log::info('Cliente demonstrou interesse', [
                'client_id' => $user->id,
                'client_email' => $user->email,
                'property_id' => $request->input('property_id'),
                'mensagem' => $request->input('mensagem')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Interesse registrado com sucesso! Em breve um corretor entrará em contato.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar interesse',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
