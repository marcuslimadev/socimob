<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratoTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratoTemplatesController extends Controller
{
    private const TIPOS_VALIDOS = ['contrato', 'compra_venda', 'aditivo', 'rescisao', 'renovacao', 'recibo'];

    /** GET /admin/financeiro/contrato-templates */
    public function index()
    {
        $templates = ContratoTemplate::get()->keyBy('tipo');

        // Garante que todos os tipos apareçam na listagem, mesmo sem registro salvo
        $result = [];
        foreach (self::TIPOS_VALIDOS as $tipo) {
            $result[$tipo] = $templates->get($tipo) ?? [
                'tipo'            => $tipo,
                'titulo'          => null,
                'intro_texto'     => null,
                'clausulas_padrao'=> [],
                'rodape_texto'    => null,
                'incluir_logo'    => true,
                'ativo'           => true,
            ];
        }

        return response()->json(['success' => true, 'items' => $result]);
    }

    /** GET /admin/financeiro/contrato-templates/{tipo} */
    public function show(string $tipo)
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS)) {
            return response()->json(['success' => false, 'message' => 'Tipo inválido'], 422);
        }

        $template = ContratoTemplate::where('tipo', $tipo)->first();

        return response()->json([
            'success' => true,
            'item'    => $template ?? [
                'tipo'            => $tipo,
                'titulo'          => null,
                'intro_texto'     => null,
                'clausulas_padrao'=> [],
                'rodape_texto'    => null,
                'incluir_logo'    => true,
                'ativo'           => true,
            ],
        ]);
    }

    /** PUT /admin/financeiro/contrato-templates/{tipo} */
    public function upsert(Request $request, string $tipo)
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS)) {
            return response()->json(['success' => false, 'message' => 'Tipo inválido'], 422);
        }

        $validator = Validator::make($request->all(), [
            'titulo'           => 'nullable|string|max:255',
            'intro_texto'      => 'nullable|string|max:20000',
            'clausulas_padrao' => 'nullable|array',
            'clausulas_padrao.*'=> 'string|max:10000',
            'rodape_texto'     => 'nullable|string|max:5000',
            'incluir_logo'     => 'nullable|boolean',
            'ativo'            => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['tipo'] = $tipo;

        $template = ContratoTemplate::updateOrCreate(
            ['tipo' => $tipo],
            $data,
        );

        return response()->json(['success' => true, 'item' => $template]);
    }

    /** DELETE /admin/financeiro/contrato-templates/{tipo} — reseta para padrão */
    public function destroy(string $tipo)
    {
        ContratoTemplate::where('tipo', $tipo)->delete();
        return response()->json(['success' => true]);
    }
}
