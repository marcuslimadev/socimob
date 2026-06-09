<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $conversations = CrmConversation::where("tenant_id", $tenantId)
            ->when($request->has("corretor"), function ($query) use ($request) {
                $query->where("assigned_user_id", $request->input("corretor"));
            })
            ->when($request->has("lead"), function ($query) use ($request) {
                $query->where("lead_id", $request->input("lead"));
            })
            ->when($request->has("imovel"), function ($query) use ($request) {
                $query->where("property_id", $request->input("imovel"));
            })
            ->when($request->has("status"), function ($query) use ($request) {
                $query->where("status", $request->input("status"));
            })
            ->when($request->has("origem"), function ($query) use ($request) {
                $query->where("source", $request->input("origem"));
            })
            ->when($request->has("data"), function ($query) use ($request) {
                $query->whereDate("created_at", $request->input("data"));
            })
            ->when($request->has("etapa"), function ($query) use ($request) {
                $query->where("stage", $request->input("etapa"));
            })
            ->with(["lead", "property", "assignedUser", "channel"])
            ->paginate();

        return response()->json(["success" => true, "data" => $conversations, "message" => "Conversas listadas com sucesso."]);
    }

    public function show(CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $conversation->load(["lead", "property", "assignedUser", "messages", "events", "tasks", "visits", "proposals"]);

        return response()->json(["success" => true, "data" => $conversation, "message" => "Detalhes da conversa."]);
    }

    public function storeSummary(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "summary" => "required|string",
            "next_action" => "nullable|string",
            "interest_level" => "nullable|integer|min:1|max:5",
            "status" => "required|string",
            "event_source" => "required|in:web,chrome_extension,api",
        ]);

        $conversation->update([
            "last_summary_at" => now(),
            "status" => $request->input("status"),
            "interest_level" => $request->input("interest_level"),
        ]);

        $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => "summary_saved",
            "title" => "Resumo da conversa atualizado",
            "description" => $request->input("summary"),
            "payload_json" => ["next_action" => $request->input("next_action")],
            "source" => $request->input("event_source"),
        ]);

        return response()->json(["success" => true, "data" => $conversation, "message" => "Resumo comercial salvo com sucesso."]);
    }

    public function storeMessage(Request $request, CrmConversation $conversation)
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

    public function storeEvent(Request $request, CrmConversation $conversation)
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
