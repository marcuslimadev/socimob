<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Http\Request;

class TenantSettingsController extends Controller
{
    /**
     * Obter configurações do tenant atual
     * GET /api/admin/settings
     */
    public function index(Request $request)
    {
        // Obter usuário do token (SimpleTokenAuth middleware já validou)
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        if (!$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $config = $tenant->config;

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'theme' => $tenant->theme,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'slogan' => $tenant->slogan,
                'primary_color' => $tenant->primary_color,
                'secondary_color' => $tenant->secondary_color,
                'contact_email' => $tenant->contact_email,
                'contact_phone' => $tenant->contact_phone,
            ],
            'config' => $config,
            'integrations_managed_by_env' => true,
        ]);
    }

    /**
     * Atualizar informações do tenant
     * PUT /api/admin/settings/tenant
     */
    public function updateTenant(Request $request)
    {
        // Obter usuário do token
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        if (!$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Validação no estilo Lumen
        $this->validate($request, [
            'name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'slogan' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'portal_finalidades' => 'nullable|array',
            'portal_finalidades.*' => 'in:venda,aluguel',
        ]);

        // Atualizar apenas campos enviados
        $tenant->update($request->only([
            'name', 
            'contact_email', 
            'contact_phone', 
            'description', 
            'logo_url', 
            'favicon_url',
            'slogan',
            'primary_color',
            'secondary_color',
        ]));

        if ($request->has('portal_finalidades')) {
            $config = $tenant->config;
            if (!$config) {
                $config = TenantConfig::create(['tenant_id' => $tenant->id]);
            }
            $config->update([
                'portal_finalidades' => $request->input('portal_finalidades'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Upload de logo e favicon
     * POST /api/admin/settings/assets
     */
    public function uploadAssets(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 401);
        }

        if (!$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->validate($request, [
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'favicon' => 'nullable|file|mimes:ico,png,svg|max:512',
        ]);

        if (!$request->hasFile('logo') && !$request->hasFile('favicon')) {
            return response()->json(['error' => 'Nenhum arquivo enviado'], 400);
        }

        $uploadsDir = public_path('uploads/tenants/' . $tenant->id);
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $updates = [];

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            if (!$logo->isValid()) {
                return response()->json(['error' => 'Logo inválido'], 400);
            }

            $logoExt = strtolower($logo->getClientOriginalExtension()) ?: 'png';
            $logoName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $logoExt;
            $logo->move($uploadsDir, $logoName);
            $updates['logo_url'] = '/uploads/tenants/' . $tenant->id . '/' . $logoName;
        }

        if ($request->hasFile('favicon')) {
            $favicon = $request->file('favicon');
            if (!$favicon->isValid()) {
                return response()->json(['error' => 'Favicon inválido'], 400);
            }

            $faviconExt = strtolower($favicon->getClientOriginalExtension()) ?: 'ico';
            $faviconName = 'favicon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $faviconExt;
            $favicon->move($uploadsDir, $faviconName);
            $updates['favicon_url'] = '/uploads/tenants/' . $tenant->id . '/' . $faviconName;
        }

        if (!empty($updates)) {
            $tenant->update($updates);
            if ($tenant->config) {
                $tenant->config->update($updates);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Assets atualizados com sucesso',
            'assets' => $updates,
        ]);
    }

    /**
     * Atualizar tema
     * PUT /api/admin/settings/theme
     */
    public function updateTheme(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'theme' => 'required|in:classico,bauhaus',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'accent_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $tenant->update([
            'theme' => $validated['theme'],
            'primary_color' => $validated['primary_color'] ?? $tenant->primary_color,
            'secondary_color' => $validated['secondary_color'] ?? $tenant->secondary_color,
        ]);

        // Atualizar cores na config também
        if ($tenant->config) {
            $tenant->config->update([
                'primary_color' => $validated['primary_color'] ?? $tenant->config->primary_color,
                'secondary_color' => $validated['secondary_color'] ?? $tenant->config->secondary_color,
                'accent_color' => $validated['accent_color'] ?? $tenant->config->accent_color,
            ]);
        }

        return response()->json([
            'message' => 'Theme updated successfully',
            'theme' => $tenant->theme,
            'colors' => [
                'primary' => $tenant->primary_color,
                'secondary' => $tenant->secondary_color,
                'accent' => $validated['accent_color'] ?? null,
            ],
        ]);
    }

    /**
     * Atualizar domínio
     * PUT /api/admin/settings/domain
     */
    public function updateDomain(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'domain' => 'required|string|max:255|unique:tenants,domain,' . $tenantId,
        ]);

        $tenant->update(['domain' => $validated['domain']]);

        return response()->json([
            'message' => 'Domain updated successfully',
            'domain' => $tenant->domain,
        ]);
    }

    /**
     * Obter configurações de email/SMTP
     * GET /api/admin/settings/email
     */
    public function getEmailSettings(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config = $tenant->config;

        return response()->json([
            'smtp_host' => $config->smtp_host,
            'smtp_port' => $config->smtp_port,
            'smtp_username' => $config->smtp_username,
            'smtp_from_email' => $config->smtp_from_email,
            'smtp_from_name' => $config->smtp_from_name,
        ]);
    }

    /**
     * Atualizar configurações de email/SMTP
     * PUT /api/admin/settings/email
     */
    public function updateEmailSettings(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'required|string',
            'smtp_from_email' => 'required|email|max:255',
            'smtp_from_name' => 'required|string|max:255',
        ]);

        $config = $tenant->config;

        if (!$config) {
            $config = TenantConfig::create([
                'tenant_id' => $tenantId,
            ]);
        }

        $config->update($validated);

        return response()->json([
            'message' => 'Email settings updated successfully',
        ]);
    }

    /**
     * Obter configurações de notificação
     * GET /api/admin/settings/notifications
     */
    public function getNotificationSettings(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $config = $tenant->config;

        return response()->json([
            'notify_new_leads' => $config->notify_new_leads,
            'notify_new_properties' => $config->notify_new_properties,
            'notify_new_messages' => $config->notify_new_messages,
            'notification_email' => $config->notification_email,
        ]);
    }

    /**
     * Atualizar configurações de notificação
     * PUT /api/admin/settings/notifications
     */
    public function updateNotificationSettings(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context'], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Verificar se o usuário é admin do tenant
        if (!$request->user()->isAdmin() || $request->user()->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'notify_new_leads' => 'nullable|boolean',
            'notify_new_properties' => 'nullable|boolean',
            'notify_new_messages' => 'nullable|boolean',
            'notification_email' => 'nullable|email|max:255',
        ]);

        $config = $tenant->config;

        if (!$config) {
            $config = TenantConfig::create([
                'tenant_id' => $tenantId,
            ]);
        }

        $config->update($validated);

        return response()->json([
            'message' => 'Notification settings updated successfully',
        ]);
    }
}


