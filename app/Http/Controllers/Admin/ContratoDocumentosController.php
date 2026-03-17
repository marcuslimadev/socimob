<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContratoDocumento;
use App\Models\ContratoLocacao;
use App\Services\ContratoDocumentoService;
use App\Services\D4SignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratoDocumentosController extends Controller
{
    public function __construct(
        private readonly ContratoDocumentoService $documentoService,
        private readonly D4SignService $d4signService,
    ) {
    }

    public function index(int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $items = ContratoDocumento::where('contrato_id', $contratoId)
            ->orderByDesc('versao')
            ->orderByRaw("case categoria when 'original' then 0 when 'revisado' then 1 when 'assinado' then 2 else 9 end")
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function gerarPdf(Request $request, int $contratoId)
    {
        $contrato = ContratoLocacao::with([
            'imovel',
            'locador',
            'locatario',
            'fiadores.pessoa',
        ])->find($contratoId);

        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|in:contrato,aditivo,rescisao,renovacao,recibo',
            'template' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $documento = $this->documentoService->gerarPdf(
            $contrato,
            $validator->validated()['tipo'],
            $validator->validated()['template'] ?? null,
        );

        $documento->refresh();

        return response()->json([
            'success' => true,
            'item' => $documento,
            'url_documento' => $documento->url_documento,
        ], 201);
    }

    public function enviarParaAssinatura(Request $request, int $contratoId, int $documentoId)
    {
        $documento = ContratoDocumento::where('contrato_id', $contratoId)->find($documentoId);
        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'Documento não encontrado'], 404);
        }

        if ($documento->categoria === 'assinado') {
            return response()->json(['success' => false, 'message' => 'Envie para assinatura a versão original ou revisada, não o arquivo já assinado.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'signatarios' => 'required|array|min:1',
            'signatarios.*.email' => 'required|email',
            'signatarios.*.nome' => 'required|string|max:200',
            'signatarios.*.papel' => 'nullable|string|max:50',
            'signatarios.*.icp_brasil' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $resultado = $this->d4signService->enviarDocumento(
            $documento,
            $validator->validated()['signatarios'],
        );

        if (!$resultado['success']) {
            return response()->json(['success' => false, 'message' => $resultado['message'] ?? 'Erro ao enviar para assinatura'], 500);
        }

        $documento->update([
            'assinatura_status' => 'aguardando',
            'd4sign_uuid' => $resultado['uuid'] ?? null,
            'd4sign_key' => $resultado['key'] ?? null,
        ]);

        return response()->json(['success' => true, 'item' => $documento->fresh()]);
    }

    public function uploadAssinado(Request $request, int $contratoId, int $documentoId)
    {
        $documento = ContratoDocumento::where('contrato_id', $contratoId)->find($documentoId);
        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'Documento não encontrado'], 404);
        }

        if ($documento->categoria === 'assinado') {
            return response()->json(['success' => false, 'message' => 'Selecione a versão original ou revisada para anexar o PDF assinado.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'arquivo' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $documentoAssinado = $this->documentoService->registrarDocumentoAssinado(
            $documento,
            $request->file('arquivo'),
            $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'item' => $documentoAssinado->fresh(),
            'url_documento' => $documentoAssinado->url_documento,
        ], 201);
    }

    public function destroy(int $contratoId, int $documentoId)
    {
        $documento = ContratoDocumento::where('contrato_id', $contratoId)->find($documentoId);
        if (!$documento) {
            return response()->json(['success' => false, 'message' => 'Documento não encontrado'], 404);
        }

        $this->documentoService->excluir($documento);

        return response()->json(['success' => true]);
    }
}
