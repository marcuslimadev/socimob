<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MessageTemplate; // Assuming a MessageTemplate model exists

class ExtensionTemplateController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;

        $templates = MessageTemplate::where("tenant_id", $tenantId)->get();

        return response()->json(["success" => true, "data" => $templates, "message" => "Templates de mensagem listados com sucesso."]);
    }
}
