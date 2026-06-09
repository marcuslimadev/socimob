<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationVisitController extends Controller
{
    public function store(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "property_id" => "required|exists:properties,id",
            "scheduled_at" => "required|date",
            "participants" => "nullable|array",
            "notes" => "nullable|string",
            "status" => "required|string",
        ]);

        $visit = $conversation->visits()->create([
            "tenant_id" => $conversation->tenant_id,
            "property_id" => $request->input("property_id"),
            "scheduled_at" => $request->input("scheduled_at"),
            "status" => $request->input("status"),
            "notes" => $request->input("notes"),
            "participants_json" => $request->input("participants"),
            "created_by" => Auth::id(),
        ]);

        $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => "visit_scheduled",
            "title" => "Visita agendada para " . $visit->scheduled_at->format("d/m/Y H:i"),
            "payload_json" => ["visit_id" => $visit->id],
            "source" => "web",
        ]);

        return response()->json(["success" => true, "data" => $visit, "message" => "Visita registrada com sucesso."]);
    }
}
