<?php

namespace App\Http\Controllers\Api\Atendimento;

use App\Http\Controllers\Controller;
use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationTaskController extends Controller
{
    public function store(Request $request, CrmConversation $conversation)
    {
        if (Auth::user()->tenant_id !== $conversation->tenant_id) {
            return response()->json(["success" => false, "message" => "Não autorizado."], 403);
        }

        $request->validate([
            "title" => "required|string",
            "description" => "nullable|string",
            "due_at" => "nullable|date",
            "assigned_user_id" => "required|exists:users,id",
            "priority" => "required|string",
        ]);

        $task = $conversation->tasks()->create([
            "tenant_id" => $conversation->tenant_id,
            "assigned_user_id" => $request->input("assigned_user_id"),
            "created_by" => Auth::id(),
            "title" => $request->input("title"),
            "description" => $request->input("description"),
            "due_at" => $request->input("due_at"),
            "priority" => $request->input("priority"),
            "status" => "pending", // Default status
        ]);

        $conversation->events()->create([
            "tenant_id" => $conversation->tenant_id,
            "user_id" => Auth::id(),
            "event_type" => "task_created",
            "title" => "Tarefa criada: " . $task->title,
            "payload_json" => ["task_id" => $task->id],
            "source" => "web",
        ]);

        return response()->json(["success" => true, "data" => $task, "message" => "Tarefa criada com sucesso."]);
    }
}
