<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    /**
     * Listar imóveis disponíveis (público)
     * 
     * GET /api/properties
     */
    public function index(Request $request)
    {
        $query = Property::where('active', 1)
            ->where('exibir_imovel', 1)
            ->where(function ($q) {
                $q->whereIn('finalidade_imovel', ['Venda', 'Venda/Aluguel'])
                    ->orWhereNull('finalidade_imovel')
                    ->orWhere('finalidade_imovel', '');
            })
            ->orderBy('created_at', 'desc');
        
        // Filtros opcionais
        
        // Busca textual geral
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('endereco', 'ILIKE', '%' . $search . '%')
                  ->orWhere('cidade', 'ILIKE', '%' . $search . '%')
                  ->orWhere('tipo_imovel', 'ILIKE', '%' . $search . '%')
                  ->orWhere('codigo', 'ILIKE', '%' . $search . '%')
                  ->orWhere('titulo', 'ILIKE', '%' . $search . '%')
                  ->orWhere('descricao', 'ILIKE', '%' . $search . '%');
            });
        }
        
        if ($request->has('tipo')) {
            $query->where('tipo_imovel', 'ILIKE', '%' . $request->tipo . '%');
        }
        
        if ($request->has('cidade')) {
            $query->where('cidade', 'ILIKE', '%' . $request->cidade . '%');
        }
        
        if ($request->has('quartos_min')) {
            $query->where('quartos', '>=', $request->quartos_min);
        }
        
        if ($request->has('preco_min')) {
            $query->where('preco', '>=', $request->preco_min);
        }
        
        if ($request->has('preco_max')) {
            $query->where('preco', '<=', $request->preco_max);
        }
        
        $properties = $query->get();
        
        return response()->json([
            'success' => true,
            'total' => $properties->count(),
            'data' => $properties
        ]);
    }
    
    /**
     * Detalhes de um imóvel específico
     * 
     * GET /api/properties/{codigo}
     */
    public function show($codigo)
    {
        $property = Property::where('codigo', $codigo)
            ->where('active', 1)
            ->where('exibir_imovel', 1)
            ->where(function ($q) {
                $q->whereIn('finalidade_imovel', ['Venda', 'Venda/Aluguel'])
                    ->orWhereNull('finalidade_imovel')
                    ->orWhere('finalidade_imovel', '');
            })
            ->first();
        
        if (!$property) {
            return response()->json([
                'success' => false,
                'error' => 'Imóvel não encontrado'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $property
        ]);
    }
}
