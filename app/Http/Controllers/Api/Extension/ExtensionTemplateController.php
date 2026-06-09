<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\MessageTemplate;

class ExtensionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        if (!Schema::hasTable("message_templates")) {
            return response()->json(["success" => true, "data" => [], "message" => "Nenhum template cadastrado."]);
        }

        $templates = MessageTemplate::where("tenant_id", $tenantId)->get();

        return response()->json(["success" => true, "data" => $templates, "message" => "Templates de mensagem listados com sucesso."]);
    }
}
