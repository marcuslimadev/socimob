<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Services\LeadDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LeadDocumentsController extends Controller
{
    public function __construct(private readonly LeadDocumentService $documents)
    {
    }

    public function index(Request $request, int $leadId)
    {
        $lead = $this->resolveLeadForTenant($leadId, $request);

        $documents = $lead->documents()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    public function store(Request $request, int $leadId)
    {
        $lead = $this->resolveLeadForTenant($leadId, $request);

        $this->validate($request, [
            'arquivo' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx',
            'tipo' => 'nullable|string|max:191',
            'nome' => 'nullable|string|max:191',
            'status' => 'nullable|string|max:50',
        ]);

        $file = $request->file('arquivo');

        $document = $this->documents->storeUploadedDocument(
            $lead,
            $file,
            $request->input('tipo'),
            $request->input('nome'),
            $request->input('status', 'pendente'),
        );

        return response()->json([
            'success' => true,
            'data' => $document,
        ], 201);
    }

    public function destroy(Request $request, int $leadId, int $documentId)
    {
        $lead = $this->resolveLeadForTenant($leadId, $request);

        $document = LeadDocument::where('lead_id', $lead->id)
            ->when($lead->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->findOrFail($documentId);

        $this->documents->deleteDocument($document);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Documento removido com sucesso',
        ]);
    }

    public function export(Request $request, int $leadId): BinaryFileResponse
    {
        $lead = $this->resolveLeadForTenant($leadId, $request);
        $documents = $lead->documents()->orderBy('created_at')->get();

        if ($documents->isEmpty()) {
            abort(404, 'Nenhum documento encontrado para este lead');
        }

        try {
            $zipPath = $this->documents->createZipForLead($lead, $documents);
        } catch (\Throwable $e) {
            Log::error('Falha ao gerar ZIP de documentos', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Não foi possível gerar o ZIP dos documentos');
        }

        if (!$zipPath) {
            abort(422, 'Nenhum documento disponível para exportação');
        }

        $fileName = basename($zipPath);

        return response()->download($zipPath, $fileName)->deleteFileAfterSend(true);
    }

    private function resolveLeadForTenant(int $id, Request $request): Lead
    {
        $tenantId = $request->attributes->get('tenant_id');

        $query = Lead::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->findOrFail($id);
    }
}
