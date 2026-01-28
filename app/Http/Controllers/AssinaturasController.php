<?php

namespace App\Http\Controllers;

use App\Models\DocumentoAssinatura;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssinaturasController extends Controller
{
    private $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function index(Request $request)
    {
        $query = DocumentoAssinatura::query();

        if ($request->attributes->has('tenant_id')) {
            $query->forTenant($request->attributes->get('tenant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('titulo', 'ILIKE', "%{$search}%");
        }

        $perPage = (int) $request->query('per_page', 15);

        $documentos = $query->orderBy('created_at', 'desc')
            ->with(['lead', 'imovel'])
            ->paginate($perPage);

        return response()->json($documentos);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'tipo' => 'nullable|string|in:contrato,procuracao,termo,outro',
            'descricao' => 'nullable|string',
            'arquivo_url' => 'nullable|string',
            'assinantes' => 'nullable|array',
            'lead_id' => 'nullable|integer',
            'imovel_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['tenant_id'] = $request->attributes->get('tenant_id');
        $data['status'] = 'pendente';
        $data['data_envio'] = now();
        $data['historico'] = [
            [
                'evento' => 'documento_criado',
                'descricao' => 'Documento criado',
                'data' => now()->toDateTimeString(),
            ]
        ];

        $documento = DocumentoAssinatura::create($data);

        // Enviar e-mail para assinantes se houver
        if (!empty($data['assinantes']) && is_array($data['assinantes'])) {
            $this->emailService->enviarDocumentoParaAssinatura($documento, $data['assinantes']);
        }

        return response()->json([
            'success' => true,
            'data' => $documento,
        ], 201);
    }

    public function show($id)
    {
        $documento = DocumentoAssinatura::with(['lead', 'imovel'])->find($id);

        if (!$documento) {
            return response()->json(['error' => 'Documento not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $documento,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pendente,assinado,cancelado,expirado',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }

        $documento = DocumentoAssinatura::find($id);

        if (!$documento) {
            return response()->json(['error' => 'Documento not found'], 404);
        }

        $oldStatus = $documento->status;
        $newStatus = $request->input('status');

        $documento->status = $newStatus;

        if ($newStatus === 'assinado' && !$documento->data_conclusao) {
            $documento->data_conclusao = now();
        }

        $historico = $documento->historico ?? [];
        $historico[] = [
            'evento' => 'status_alterado',
            'descricao' => "Status alterado de '{$oldStatus}' para '{$newStatus}'",
            'data' => now()->toDateTimeString(),
        ];
        $documento->historico = $historico;

        $documento->save();

        // Enviar e-mail se documento foi concluído
        if ($newStatus === 'assinado') {
            $destinatarios = $this->getDestinatariosAssinatura($documento);
            $this->emailService->enviarAssinaturaConcluida($documento, $destinatarios);
        }

        return response()->json([
            'success' => true,
            'data' => $documento,
        ]);
    }

    public function destroy($id)
    {
        $documento = DocumentoAssinatura::find($id);

        if (!$documento) {
            return response()->json(['error' => 'Documento not found'], 404);
        }

        $documento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento deletado com sucesso',
        ]);
    }

    /**
     * Obter lista de destinatários para notificações de assinatura
     */
    private function getDestinatariosAssinatura($documento)
    {
        $destinatarios = [];

        // Adicionar emails dos assinantes
        if (!empty($documento->assinantes) && is_array($documento->assinantes)) {
            foreach ($documento->assinantes as $assinante) {
                if (!empty($assinante['email'])) {
                    $destinatarios[] = $assinante['email'];
                }
            }
        }

        // Adicionar emails de administradores
        $admins = app('db')->table('users')
            ->where('is_active', true)
            ->where('role', 'admin')
            ->pluck('email')
            ->toArray();

        $destinatarios = array_merge($destinatarios, $admins);

        return array_unique(array_filter($destinatarios));
    }
}
