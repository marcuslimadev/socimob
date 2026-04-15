<?php
namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    private function applyPurposeFilter($query, ?string $purpose): void
    {
        $normalized = strtolower(trim((string) $purpose));
        if ($normalized === '') {
            return;
        }

        $query->where(function ($inner) use ($normalized) {
            if ($normalized === 'venda') {
                $inner->orWhereRaw('LOWER(COALESCE(finalidade_imovel, \'\')) LIKE ?', ['%vend%']);
            }

            if ($normalized === 'aluguel' || $normalized === 'locacao') {
                $inner->orWhereRaw('LOWER(COALESCE(finalidade_imovel, \'\')) LIKE ?', ['%alug%']);
                $inner->orWhereRaw('LOWER(COALESCE(finalidade_imovel, \'\')) LIKE ?', ['%loca%']);
            }

            if ($normalized === 'temporada') {
                $inner->orWhereRaw('LOWER(COALESCE(finalidade_imovel, \'\')) LIKE ?', ['%temporad%']);
            }

            if (!in_array($normalized, ['venda', 'aluguel', 'locacao', 'temporada'], true)) {
                $inner->orWhereRaw('LOWER(COALESCE(finalidade_imovel, \'\')) LIKE ?', ['%' . $normalized . '%']);
            }
        });
    }

    /**
     * Listar imóveis disponíveis (público)
     * 
     * GET /api/properties
     */
    public function index(Request $request)
    {
        $query = Property::orderBy('created_at', 'desc');
                if ($request->has('finalidade') && !empty($request->finalidade)) {
                    $this->applyPurposeFilter($query, $request->finalidade);
                }

        
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
        $property = Property::where(function ($query) use ($codigo) {
            $query->where('codigo', $codigo)
                ->orWhere('codigo_imovel', $codigo)
                ->orWhere('referencia_imovel', $codigo);
        })->first();
        
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
