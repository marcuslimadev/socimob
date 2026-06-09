<?php

namespace App\Services;

use App\Models\CrmConversation;
use App\Models\Lead;
use Illuminate\Support\Facades\Auth;

class ConversationService
{
    public function createConversationFromExtension(array $data)
    {
        $user = Auth::user();

        // Ensure lead belongs to the same tenant
        $lead = Lead::where("tenant_id", $user->tenant_id)->findOrFail($data["lead_id"]);

        $conversation = CrmConversation::create([
            "tenant_id" => $user->tenant_id,
            "lead_id" => $lead->id,
            "property_id" => $data["property_id"] ?? null,
            "assigned_user_id" => $data["assigned_user_id"] ?? $user->id,
            "source" => $data["source"],
            "external_identifier_hash" => hash("sha256", $data["whatsapp_chat_identifier"]),
            "contact_name" => $data["contact_name"],
            "contact_phone" => $data["contact_phone"],
            "status" => "open", // Default status
            "stage" => "initial_contact", // Default stage
            "created_by" => $user->id,
        ]);

        $conversation->events()->create([
            "tenant_id" => $user->tenant_id,
            "user_id" => $user->id,
            "event_type" => "conversation_linked",
            "title" => "Conversa vinculada ao lead",
            "payload_json" => ["whatsapp_chat_identifier" => $data["whatsapp_chat_identifier"]],
            "source" => "chrome_extension",
        ]);

        return $conversation;
    }

    public function updateConversationSummary(CrmConversation $conversation, array $data)
    {
        $conversation->update([
            "last_summary_at" => now(),
            "status" => $data["status"],
            "interest_level" => $data["interest_level"] ?? null,
        ]);

        $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => "summary_saved",
            "title" => "Resumo da conversa atualizado",
            "description" => $data["summary"],
            "payload_json" => ["next_action" => $data["next_action"] ?? null],
            "source" => $data["event_source"] ?? "web",
        ]);

        return $conversation;
    }
}
