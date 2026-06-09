<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationMessageController extends Controller
{
    public function store(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "direction" => "required|in:inbound,outbound",
            "message_type" => "required|in:text,image,file,audio,system",
            "body" => "required|string",
            "external_message_id" => "nullable|string",
            "sent_at" => "required|date",
            "metadata" => "nullable|array",
        ]);

        $message = $conversation->messages()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "direction" => $request->input("direction"),
            "message_type" => $request->input("message_type"),
            "body" => $request->input("body"),
            "external_message_id" => $request->input("external_message_id"),
            "external_sent_at" => $request->input("sent_at"),
            "metadata_json" => $request->input("metadata"),
        ]);

        $conversation->update(["last_message_at" => now()]);

        return response()->json(["success" => true, "data" => $message, "message" => "Mensagem registrada com sucesso."]);
    }
}
