<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;

class ExtensionLeadController extends Controller
{
    public function search(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $query = $request->input("q");

        $leads = Lead::where("tenant_id", $tenantId)
            ->where(function ($q) use ($query) {
                $q->where("nome", "like", "%" . $query . "%")
                    ->orWhere("telefone", "like", "%" . $query . "%")
                    ->orWhere("whatsapp", "like", "%" . $query . "%")
                    ->orWhere("email", "like", "%" . $query . "%")
                    ->orWhere("observacoes", "like", "%" . $query . "%");
            })
            ->get();

        return response()->json(["success" => true, "data" => $leads, "message" => "Leads encontrados com sucesso."]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            "name" => "required|string",
            "phone" => "required|string",
            "email" => "nullable|email",
            "origin" => "nullable|string",
            "observations" => "nullable|string",
            "property_id" => "nullable|exists:properties,id",
        ]);

        $lead = Lead::create([
            "tenant_id" => $user->tenant_id,
            "nome" => $request->input("name"),
            "telefone" => $request->input("phone"),
            "whatsapp" => $request->input("phone"),
            "email" => $request->input("email"),
            "observacoes" => trim(implode("\n", array_filter([
                $request->input("origin") ? "Origem: " . $request->input("origin") : null,
                $request->input("observations"),
            ]))),
            "user_id" => $user->id,
        ]);

        return response()->json(["success" => true, "data" => $lead, "message" => "Lead criado com sucesso."]);
    }

    /**
     * Adiciona uma nota rápida ao lead (salva em observacoes com timestamp).
     */
    public function addNote(Request $request, Lead $lead)
    {
        $user = $request->user();

        if ($lead->tenant_id !== $user->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "note" => "required|string|max:2000",
            "source" => "nullable|string",
        ]);

        $timestamp = now()->format("d/m/Y H:i");
        $source = $request->input("source", "chrome_extension");
        $newNote = "[{$timestamp} via extensão] " . $request->input("note");

        $existingObs = $lead->observacoes ? $lead->observacoes . "\n\n" : "";
        $lead->update(["observacoes" => $existingObs . $newNote]);

        return response()->json(["success" => true, "data" => $lead, "message" => "Nota adicionada ao lead."]);
    }

    /**
     * Atualiza a classificação (status de qualificação) do lead.
     */
    public function updateStatus(Request $request, Lead $lead)
    {
        $user = $request->user();

        if ($lead->tenant_id !== $user->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "classificacao" => "required|string|in:frio,morno,quente,negociando,fechado,perdido",
        ]);

        $lead->update(["classificacao" => $request->input("classificacao")]);

        return response()->json(["success" => true, "data" => $lead, "message" => "Status do lead atualizado."]);
    }
}
