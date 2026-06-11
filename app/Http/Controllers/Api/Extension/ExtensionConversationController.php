<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;

class ExtensionConversationController extends Controller
{
    public function link(Request $request)
    {
        $user = $request->user();

        $request->validate([
            "lead_id" => "required|exists:leads,id",
            "property_id" => "nullable|exists:properties,id",
            "contact_name" => "required|string",
            "contact_phone" => "required|string",
            "whatsapp_chat_identifier" => "required|string",
            "source" => "required|string",
            "assigned_user_id" => "nullable|exists:users,id",
        ]);

        $lead = \App\Models\Lead::where("tenant_id", $user->tenant_id)->findOrFail($request->input("lead_id"));

        $hash = hash("sha256", $request->input("whatsapp_chat_identifier"));

        // Return existing conversation if already linked to avoid duplicates
        $existing = CrmConversation::where("tenant_id", $user->tenant_id)
            ->where("external_identifier_hash", $hash)
            ->with(["lead"])
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                "success" => true,
                "data" => $existing,
                "message" => "Conversa já vinculada ao lead.",
                "already_linked" => true,
            ]);
        }

        $conversation = CrmConversation::create([
            "tenant_id" => $user->tenant_id,
            "lead_id" => $lead->id,
            "property_id" => $request->input("property_id"),
            "assigned_user_id" => $request->input("assigned_user_id", $user->id),
            "source" => $request->input("source"),
            "external_identifier_hash" => $hash,
            "contact_name" => $request->input("contact_name"),
            "contact_phone" => $request->input("contact_phone"),
            "status" => "open",
            "stage" => "initial_contact",
            "created_by" => $user->id,
        ]);

        $conversation->events()->create([
            "tenant_id" => $user->tenant_id,
            "user_id" => $user->id,
            "event_type" => "conversation_linked",
            "title" => "Conversa vinculada ao lead via extensão Chrome",
            "payload_json" => ["whatsapp_chat_identifier" => $request->input("whatsapp_chat_identifier")],
            "source" => "chrome_extension",
        ]);

        $conversation->load(["lead"]);

        return response()->json(["success" => true, "data" => $conversation, "message" => "Conversa vinculada com sucesso."]);
    }

    /**
     * Busca uma conversa existente pelo URL/identificador do WhatsApp Web.
     */
    public function find(Request $request)
    {
        $user = $request->user();

        $request->validate([
            "url" => "required|string",
        ]);

        $hash = hash("sha256", $request->input("url"));

        $conversation = CrmConversation::where("tenant_id", $user->tenant_id)
            ->where("external_identifier_hash", $hash)
            ->with(["lead", "assignedUser"])
            ->latest()
            ->first();

        return response()->json([
            "success" => true,
            "data" => $conversation,
        ]);
    }
}
