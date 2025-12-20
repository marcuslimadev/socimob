<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Property;
use App\Services\PropertyLikesTablesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LikesController extends Controller
{
    /**
     * POST /api/portal/likes/{propertyId}
     */
    public function like(Request $request, $propertyId)
    {
        $tenantId = $request->attributes->get('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        PropertyLikesTablesManager::ensurePropertyLikesTableExists();

        $property = Property::where('tenant_id', $tenantId)
            ->where('id', $propertyId)
            ->first();

        if (!$property) {
            return response()->json(['error' => 'Property not found'], 404);
        }

        $existing = DB::table('property_likes')
            ->where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->where('user_id', $user->id)
            ->first();

        if (!$existing) {
            $leadId = Lead::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->value('id');

            DB::table('property_likes')->insert([
                'tenant_id' => $tenantId,
                'property_id' => $propertyId,
                'user_id' => $user->id,
                'lead_id' => $leadId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $count = DB::table('property_likes')
            ->where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->count();

        return response()->json([
            'success' => true,
            'liked' => true,
            'count' => (int) $count,
        ]);
    }

    /**
     * GET /api/portal/likes
     */
    public function list(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        PropertyLikesTablesManager::ensurePropertyLikesTableExists();

        $likes = DB::table('property_likes')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->pluck('property_id');

        return response()->json([
            'success' => true,
            'data' => $likes,
        ]);
    }
}
