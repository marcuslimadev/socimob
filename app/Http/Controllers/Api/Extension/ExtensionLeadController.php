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
}
