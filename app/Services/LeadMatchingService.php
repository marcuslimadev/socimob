<?php

namespace App\Services;

use App\Models\Lead;

class LeadMatchingService
{
    public function searchLeads(string $query, int $tenantId)
    {
        return Lead::where("tenant_id", $tenantId)
            ->where(function ($q) use ($query) {
                $q->where("nome", "like", "%" . $query . "%")
                    ->orWhere("telefone", "like", "%" . $query . "%")
                    ->orWhere("whatsapp", "like", "%" . $query . "%")
                    ->orWhere("email", "like", "%" . $query . "%")
                    ->orWhere("observacoes", "like", "%" . $query . "%");
            })
            ->get();
    }
}
