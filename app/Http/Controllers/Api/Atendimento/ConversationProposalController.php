<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationProposalController extends Controller
{
    public function store(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "property_id" => "required|exists:properties,id",
            "amount" => "required|numeric|min:0",
            "proposal_type" => "required|in:purchase,rent,other",
            "status" => "required|string",
            "notes" => "nullable|string",
        ]);

        $proposal = $conversation->proposals()->create([
            "tenant_id" => $conversation->tenant_id,
            "property_id" => $request->input("property_id"),
            "amount" => $request->input("amount"),
            "proposal_type" => $request->input("proposal_type"),
            "status" => $request->input("status"),
            "notes" => $request->input("notes"),
            "created_by" => Auth::id(),
        ]);

        $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => "proposal_created",
            "title" => "Proposta registrada: " . $proposal->amount,
            "payload_json" => ["proposal_id" => $proposal->id],
            "source" => "web",
        ]);

        return response()->json(["success" => true, "data" => $proposal, "message" => "Proposta registrada com sucesso."]);
    }
}
