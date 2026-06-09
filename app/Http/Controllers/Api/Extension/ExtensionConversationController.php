<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ExtensionConversationController extends Controller
{
    public function link(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            "lead_id" => "required|exists:leads,id",
            "property_id" => "nullable|exists:properties,id",
            "contact_name" => "required|string",
            "contact_phone" => "required|string",
            "whatsapp_chat_identifier" => "required|string",
            "source" => "required|string",
            "assigned_user_id" => "nullable|exists:users,id",
        ]);

        // Ensure lead belongs to the same tenant
        $lead = \App\Models\Lead::where("tenant_id", $user->tenant_id)->findOrFail($request->input("lead_id"));

        $conversation = CrmConversation::create([
            "tenant_id" => $user->tenant_id,
            "lead_id" => $lead->id,
            "property_id" => $request->input("property_id"),
            "assigned_user_id" => $request->input("assigned_user_id", $user->id),
            "source" => $request->input("source"),
            "external_identifier_hash" => hash("sha256", $request->input("whatsapp_chat_identifier")),
            "contact_name" => $request->input("contact_name"),
            "contact_phone" => $request->input("contact_phone"),
            "status" => "open", // Default status
            "stage" => "initial_contact", // Default stage
            "created_by" => $user->id,
        ]);

        $conversation->events()->create([
            "tenant_id" => $user->tenant_id,
            "user_id" => $user->id,
            "event_type" => "conversation_linked",
            "title" => "Conversa vinculada ao lead",
            "payload_json" => ["whatsapp_chat_identifier" => $request->input("whatsapp_chat_identifier")],
            "source" => "chrome_extension",
        ]);

        return response()->json(["success" => true, "data" => $conversation, "message" => "Conversa vinculada com sucesso."]);
    }
}
