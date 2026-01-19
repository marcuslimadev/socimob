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

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
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
                'metadata' => $tenant->metadata,
                'razao_social' => $tenant->metadata['razao_social'] ?? null,
                'cnpj' => $tenant->metadata['cnpj'] ?? null,
                'endereco' => $tenant->metadata['endereco'] ?? null,
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

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
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
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:30',
            'endereco' => 'nullable|string|max:255',
            'portal_finalidades' => 'nullable|array',
            'portal_finalidades.*' => 'in:venda,aluguel',
        ]);

        $metadataUpdates = [];
        foreach (['razao_social', 'cnpj', 'endereco'] as $metadataKey) {
            if ($request->exists($metadataKey)) {
                $metadataUpdates[$metadataKey] = $request->input($metadataKey);
            }
        }

        $tenantUpdates = $request->only([
            'name',
            'contact_email',
            'contact_phone',
            'description',
            'logo_url',
            'favicon_url',
            'slogan',
            'primary_color',
            'secondary_color',
        ]);

        if (!empty($metadataUpdates)) {
            $tenantUpdates['metadata'] = array_merge($tenant->metadata ?? [], $metadataUpdates);
        }

        // Atualizar apenas campos enviados
        if (!empty($tenantUpdates)) {
            $tenant->update($tenantUpdates);
        }

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

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
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
     * Atualizar configurações do tenant (nome, logo, dados da empresa)
     * POST /api/admin/tenant/config
     */
    public function updateConfig(Request $request)
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

        // Validação
        $this->validate($request, [
            'sistema_nome' => 'nullable|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string', // base64 ou URL
        ]);

        // Preparar updates
        $updates = [];
        
        if ($request->has('sistema_nome')) {
            $updates['name'] = $request->input('sistema_nome');
        }
        
        if ($request->has('telefone')) {
            $updates['contact_phone'] = $request->input('telefone');
        }
        
        if ($request->has('email')) {
            $updates['contact_email'] = $request->input('email');
        }

        // Logo: se for base64, salvar como arquivo
        if ($request->has('logo_url') && $request->input('logo_url')) {
            $logoData = $request->input('logo_url');
            
            // Verificar se é base64
            if (preg_match('/^data:image\\/(\w+);base64,/', $logoData, $matches)) {
                $extension = $matches[1];
                $base64String = substr($logoData, strpos($logoData, ',') + 1);
                $imageData = base64_decode($base64String);
                
                // Criar diretório se não existir
                $uploadsDir = public_path('uploads/tenants/' . $tenant->id);
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                // Salvar arquivo
                $fileName = 'logo_' . time() . '.' . $extension;
                $filePath = $uploadsDir . '/' . $fileName;
                file_put_contents($filePath, $imageData);
                
                $updates['logo_url'] = '/uploads/tenants/' . $tenant->id . '/' . $fileName;
            } else {
                // Se não for base64, usar como está (URL)
                $updates['logo_url'] = $logoData;
            }
        }

        // Atualizar tenant
        $tenant->update($updates);

        // Atualizar ou criar config com dados adicionais
        $config = $tenant->config;
        if (!$config) {
            $config = TenantConfig::create(['tenant_id' => $tenant->id]);
        }
        
        $configUpdates = [];
        if ($request->has('razao_social')) {
            $configUpdates['razao_social'] = $request->input('razao_social');
        }
        if ($request->has('cnpj')) {
            $configUpdates['cnpj'] = $request->input('cnpj');
        }
        if ($request->has('endereco')) {
            $configUpdates['endereco'] = $request->input('endereco');
        }
        
        if (!empty($configUpdates)) {
            $config->update($configUpdates);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configurações atualizadas com sucesso',
            'logo_url' => $tenant->logo_url,
            'tenant' => [
                'name' => $tenant->name,
                'contact_phone' => $tenant->contact_phone,
                'contact_email' => $tenant->contact_email,
                'logo_url' => $tenant->logo_url,
            ]
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

    /**
     * Obter prompt customizado da IA
     * GET /api/admin/settings/ai-prompt
     */
    public function getAiPrompt(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $prompt = \App\Models\AppSetting::getValue('ai_prompt_custom', null, $user->tenant_id);

        return response()->json([
            'prompt' => $prompt,
            'using_default' => empty($prompt),
        ]);
    }

    /**
     * Salvar prompt customizado da IA
     * POST /api/admin/settings/ai-prompt
     */
    public function saveAiPrompt(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $this->validate($request, [
            'prompt' => 'nullable|string|max:2000',
        ]);

        $prompt = $request->input('prompt', '');
        
        // Se vazio, remove a customização (volta ao padrão)
        if (empty(trim($prompt))) {
            \App\Models\AppSetting::setValue('ai_prompt_custom', null, $user->tenant_id);
            return response()->json([
                'success' => true,
                'message' => 'Prompt resetado. Sistema usará o padrão.',
                'using_default' => true,
            ]);
        }

        // Salvar prompt customizado
        \App\Models\AppSetting::setValue('ai_prompt_custom', $prompt, $user->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Prompt customizado salvo com sucesso',
            'prompt' => $prompt,
            'using_default' => false,
        ]);
    }

    /**
     * Resetar prompt (voltar ao padrão)
     * DELETE /api/admin/settings/ai-prompt
     */
    public function deleteAiPrompt(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        \App\Models\AppSetting::setValue('ai_prompt_custom', null, $user->tenant_id);

        return response()->json([
            'success' => true,
            'message' => 'Prompt resetado para o padrão do sistema',
            'using_default' => true,
        ]);
    }

    /**
     * Obter status do atendimento automático
     * GET /api/admin/settings/atendimento-automatico
     */
    public function getAtendimentoAutomatico(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        // DESATIVADO por padrão - Admin precisa ativar manualmente
        $ativo = \App\Models\AppSetting::getValue('atendimento_automatico_ativo', false, $user->tenant_id);

        return response()->json([
            'ativo' => (bool) $ativo,
        ]);
    }

    /**
     * Definir status do atendimento automático
     * POST /api/admin/settings/atendimento-automatico
     */
    public function setAtendimentoAutomatico(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $this->validate($request, [
            'ativo' => 'required|boolean',
        ]);

        $ativo = $request->input('ativo');
        
        \App\Models\AppSetting::setValue('atendimento_automatico_ativo', $ativo, $user->tenant_id);

        $mensagem = $ativo 
            ? 'Atendimento automático ATIVADO. Novos leads da Chaves na Mão entrarão automaticamente em atendimento via WhatsApp.'
            : 'Atendimento automático DESATIVADO. Novos leads não entrarão automaticamente em atendimento.';

        return response()->json([
            'success' => true,
            'message' => $mensagem,
            'ativo' => (bool) $ativo,
        ]);
    }

    /**
     * Obter status do atendimento automático
     * GET /api/admin/settings/auto-atendimento
     */
    public function getAutoAtendimento(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $enabled = \App\Models\AppSetting::getValue('auto_atendimento_enabled', 'true', $user->tenant_id);
        
        return response()->json([
            'enabled' => $enabled === 'true' || $enabled === true || $enabled === '1',
        ]);
    }

    /**
     * Atualizar status do atendimento automático
     * POST /api/admin/settings/auto-atendimento
     */
    public function setAutoAtendimento(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $this->validate($request, [
            'enabled' => 'required|boolean',
        ]);

        $enabled = $request->input('enabled', true);
        
        \App\Models\AppSetting::setValue('auto_atendimento_enabled', $enabled ? 'true' : 'false', $user->tenant_id);

        \Illuminate\Support\Facades\Log::info('[TenantSettings] Atendimento automático alterado', [
            'tenant_id' => $user->tenant_id,
            'enabled' => $enabled,
            'user' => $user->name ?? $user->email
        ]);

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Atendimento automático ativado' : 'Atendimento automático desativado',
            'enabled' => $enabled,
        ]);
    }
}
