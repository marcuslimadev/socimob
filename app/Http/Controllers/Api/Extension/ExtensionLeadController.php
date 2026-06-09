<?php

namespace App\Http\Controllers\Api\Extension;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Lead; // Assuming a Lead model exists

class ExtensionLeadController extends Controller
{
    public function search(Request $request)
    {
        $tenantId = Auth::user()->tenant_id;
        $query = $request->input("q");

        $leads = Lead::where("tenant_id", $tenantId)
            ->where(function ($q) use ($query) {
                $q->where("name", "like", "%" . $query . "%")
                    ->orWhere("phone", "like", "%" . $query . "%")
                    ->orWhere("email", "like", "%" . $query . "%")
                    ->orWhere("property_code", "like", "%" . $query . "%"); // Assuming property_code exists on Lead
            })
            ->get();

        return response()->json(["success" => true, "data" => $leads, "message" => "Leads encontrados com sucesso."]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            "name" => "required|string",
            "phone" => "required|string",
            "email" => "nullable|email",
            "origin" => "nullable|string",
            "observations" => "nullable|string",
            "property_id" => "nullable|exists:properties,id",
        ]);

        $lead = Lead::create([
            "tenant_id" => $user->tenant_id,
            "name" => $request->input("name"),
            "phone" => $request->input("phone"),
            "email" => $request->input("email"),
            "origin" => $request->input("origin"),
            "observations" => $request->input("observations"),
            "property_id" => $request->input("property_id"),
            "created_by" => $user->id,
        ]);

        return response()->json(["success" => true, "data" => $lead, "message" => "Lead criado com sucesso."]);
    }
}
