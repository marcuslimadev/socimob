<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
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
            
            \Log::info('Cliente demonstrou interesse', [
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
