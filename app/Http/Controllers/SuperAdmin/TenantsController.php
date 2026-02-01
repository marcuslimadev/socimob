<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TenantsController extends Controller
{
    /**
     * Listar todos os tenants
     * GET /api/superadmin/tenants
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized - Super admin only'], 403);
        }

        $tenants = Tenant::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Detalhes de um tenant
     * GET /api/superadmin/tenants/{id}
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized - Super admin only'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        return response()->json([
            'success' => true,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Atualizar tenant
     * PUT /api/superadmin/tenants/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized - Super admin only'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'domain' => 'sometimes|string|max:255|unique:tenants,domain,' . $id,
            'slug' => 'sometimes|string|max:255|unique:tenants,slug,' . $id,
            'contact_email' => 'sometimes|nullable|email|max:255',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'logo_url' => 'sometimes|nullable|url|max:500',
            'primary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'sometimes|nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'subscription_status' => 'sometimes|in:active,inactive,suspended,expired',
            'is_active' => 'sometimes|boolean',
            // Integration fields
            'twilio_account_sid' => 'sometimes|nullable|string',
            'twilio_auth_token' => 'sometimes|nullable|string',
            'twilio_whatsapp_from' => 'sometimes|nullable|string',
            'twilio_template_welcome_sid' => 'sometimes|nullable|string',
            'openai_api_key' => 'sometimes|nullable|string',
            'openai_model' => 'sometimes|nullable|string',
            'ai_assistant_name' => 'sometimes|nullable|string',
            'mail_host' => 'sometimes|nullable|string',
            'mail_port' => 'sometimes|nullable|integer',
            'mail_username' => 'sometimes|nullable|string',
            'mail_password' => 'sometimes|nullable|string',
            'mail_encryption' => 'sometimes|nullable|string',
            'mail_from_address' => 'sometimes|nullable|email',
            'mail_from_name' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $tenant->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->fresh(),
        ]);
    }

    /**
     * Criar novo tenant
     * POST /api/superadmin/tenants
     */
    public function store(Request $request)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized - Super admin only'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $tenant = Tenant::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tenant created successfully',
            'tenant' => $tenant,
        ], 201);
    }

    /**
     * Deletar tenant
     * DELETE /api/superadmin/tenants/{id}
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized - Super admin only'], 403);
        }

        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully',
        ]);
    }
}
