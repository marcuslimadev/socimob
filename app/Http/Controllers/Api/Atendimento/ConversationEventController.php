<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationEventController extends Controller
{
    public function store(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "event_type" => "required|string",
            "title" => "required|string",
            "description" => "nullable|string",
            "payload" => "nullable|array",
            "source" => "required|string",
        ]);

        $event = $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => $request->input("event_type"),
            "title" => $request->input("title"),
            "description" => $request->input("description"),
            "payload_json" => $request->input("payload"),
            "source" => $request->input("source"),
        ]);

        return response()->json(["success" => true, "data" => $event, "message" => "Evento registrado com sucesso."]);
    }
}
