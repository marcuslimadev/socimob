<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Auth;

class LeadMatchingService
{
    public function searchLeads(string $query, int $tenantId)
    {
        return Lead::where("tenant_id", $tenantId)
            ->where(function ($q) use ($query) {
                $q->where("name", "like", "%" . $query . "%")
                    ->orWhere("phone", "like", "%" . $query . "%")
                    ->orWhere("email", "like", "%" . $query . "%")
                    ->orWhere("property_code", "like", "%" . $query . "%");
            })
            ->get();
    }

    public function createLead(array $data)
    {
        $user = Auth::user();
        return Lead::create([
            "tenant_id" => $user->tenant_id,
            "name" => $data["name"],
            "phone" => $data["phone"],
            "email" => $data["email"] ?? null,
            "origin" => $data["origin"] ?? null,
            "observations" => $data["observations"] ?? null,
            "property_id" => $data["property_id"] ?? null,
            "created_by" => $user->id,
        ]);
    }
}
