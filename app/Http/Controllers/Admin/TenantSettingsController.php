<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;


use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

        // Admin e super_admin podem acessar
        if (!in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Super admin pode visualizar qualquer tenant; admin apenas o próprio
        $viewAsTenantId = $user->role === 'super_admin'
            ? ($request->input('tenant_id') ?? $user->tenant_id)
            : $user->tenant_id;
        
        $tenant = Tenant::find($viewAsTenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $config = $tenant->config;
        $configData = $config ? $config->toArray() : null;

        if (is_array($configData)) {
            $configData['google_calendar_embed_url'] = $config->metadata['google_calendar_embed_url'] ?? null;
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'domain' => $tenant->domain,
                'theme' => $tenant->theme,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'mascot_url' => $tenant->mascot_url,
                'watermark_url' => $tenant->watermark_url,
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
            'config' => $configData,
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

        // Admin e super_admin podem atualizar
        if (!in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Super admin pode atualizar qualquer tenant; admin apenas o próprio
        $tenantId = $user->role === 'super_admin'
            ? ($request->input('tenant_id') ?? $user->tenant_id)
            : $user->tenant_id;
        
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Validação no estilo Lumen
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'mascot_url' => 'nullable|string|max:500',
            'watermark_url' => 'nullable|string|max:500',
            'slogan' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:30',
            'endereco' => 'nullable|string|max:255',
            'portal_finalidades' => 'nullable|array',
            'portal_finalidades.*' => 'in:venda,aluguel',
            'api_key_openai' => 'nullable|string',
            // Tenant Config fields
            'config.api_key_pagar_me' => 'nullable|string',
            'config.api_key_apm_imoveis' => 'nullable|string',
            'config.api_key_neca' => 'nullable|string',
            'config.accent_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
            'config.font_primary' => 'nullable|string|max:200',
            'config.font_secondary' => 'nullable|string|max:200',
            'config.font_url' => 'nullable|string|max:500',
            'config.smtp_host' => 'nullable|string|max:255',
            'config.smtp_port' => 'nullable|integer',
            'config.smtp_username' => 'nullable|string|max:255',
            'config.smtp_password' => 'nullable|string',
            'config.smtp_from_email' => 'nullable|email|max:255',
            'config.smtp_from_name' => 'nullable|string|max:255',
            'config.notify_new_leads' => 'nullable|boolean',
            'config.notify_new_properties' => 'nullable|boolean',
            'config.notify_new_messages' => 'nullable|boolean',
            'config.notification_email' => 'nullable|email|max:255',
            'config.max_images_per_property' => 'nullable|integer|min:1|max:100',
            'config.max_properties' => 'nullable|integer|min:1',
            'config.require_approval_for_properties' => 'nullable|boolean',
            'config.max_leads' => 'nullable|integer|min:1',
            'config.auto_assign_leads' => 'nullable|boolean',
            'config.twilio_account_sid' => 'nullable|string',
            'config.twilio_auth_token' => 'nullable|string',
            'config.twilio_whatsapp_from' => 'nullable|string|max:50',
            'config.whatsapp_number' => 'nullable|string|max:30',
            'config.google_calendar_embed_url' => 'nullable|url|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $metadataUpdates = [];
        foreach (['razao_social', 'cnpj', 'endereco'] as $metadataKey) {
            if ($request->exists($metadataKey)) {
                $metadataUpdates[$metadataKey] = $request->input($metadataKey);
            }
        }
        
        // API Key OpenAI vai no metadata também
        if ($request->has('api_key_openai') && $request->input('api_key_openai')) {
            $metadataUpdates['api_key_openai'] = $request->input('api_key_openai');
        }

        $tenantUpdates = $request->only([
            'name',
            'contact_email',
            'contact_phone',
            'description',
            'logo_url',
            'favicon_url',
            'mascot_url',
            'watermark_url',
            'slogan',
            'primary_color',
            'secondary_color',
            // Integration fields from migration
            'twilio_account_sid',
            'twilio_auth_token',
            'twilio_whatsapp_from',
            'twilio_template_welcome_sid',
            'openai_api_key',
            'openai_model',
            'ai_assistant_name',
            'mail_driver',
            'mail_host',
            'mail_port',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'mail_from_address',
            'mail_from_name',
        ]);

        if (!empty($metadataUpdates)) {
            $tenantUpdates['metadata'] = array_merge($tenant->metadata ?? [], $metadataUpdates);
        }

        // Atualizar apenas campos enviados
        if (!empty($tenantUpdates)) {
            $tenant->update($tenantUpdates);
        }

        // Atualizar tenant_config
        if ($request->has('config')) {
            $config = $tenant->config;
            if (!$config) {
                $config = TenantConfig::create(['tenant_id' => $tenant->id]);
            }
            
            $configUpdates = [];
            $configData = $request->input('config', []);
            
            // Campos permitidos para atualização
            $allowedConfigFields = [
                'api_key_pagar_me',
                'api_key_apm_imoveis',
                'api_key_neca',
                'accent_color',
                'font_primary',
                'font_secondary',
                'font_url',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_from_email',
                'smtp_from_name',
                'notify_new_leads',
                'notify_new_properties',
                'notify_new_messages',
                'notification_email',
                'max_images_per_property',
                'max_properties',
                'require_approval_for_properties',
                'max_leads',
                'auto_assign_leads',
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_whatsapp_from',
                'portal_finalidades',
                'whatsapp_number',
            ];
            
            foreach ($allowedConfigFields as $field) {
                if (array_key_exists($field, $configData)) {
                    $configUpdates[$field] = $configData[$field];
                }
            }

            if (array_key_exists('google_calendar_embed_url', $configData)) {
                $metadata = $config->metadata ?? [];
                $googleCalendarEmbedUrl = trim((string) ($configData['google_calendar_embed_url'] ?? ''));

                if ($googleCalendarEmbedUrl === '') {
                    unset($metadata['google_calendar_embed_url']);
                } else {
                    $metadata['google_calendar_embed_url'] = $googleCalendarEmbedUrl;
                }

                $configUpdates['metadata'] = $metadata;
            }
            
            if (!empty($configUpdates)) {
                $config->update($configUpdates);
            }
        }

        if ($request->has('portal_finalidades') && !$request->has('config.portal_finalidades')) {
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
     * Método único para atualizar configurações (atalho para updateTenant)
     * PUT /api/admin/settings
     */
    public function update(Request $request)
    {
        return $this->updateTenant($request);
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

        $validator = Validator::make($request->all(), [
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'favicon' => 'nullable|file|mimes:ico,png,svg|max:512',
            'mascot' => 'nullable|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'watermark' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        if (!$request->hasFile('logo') && !$request->hasFile('favicon') && !$request->hasFile('mascot') && !$request->hasFile('watermark')) {
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

        if ($request->hasFile('mascot')) {
            $mascot = $request->file('mascot');
            if (!$mascot->isValid()) {
                return response()->json(['error' => 'Mascote inválido'], 400);
            }

            $mascotExt = strtolower($mascot->getClientOriginalExtension()) ?: 'png';
            $mascotName = 'mascot_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $mascotExt;
            $mascot->move($uploadsDir, $mascotName);
            $updates['mascot_url'] = '/uploads/tenants/' . $tenant->id . '/' . $mascotName;
        }

        if ($request->hasFile('watermark')) {
            $watermark = $request->file('watermark');
            if (!$watermark->isValid()) {
                return response()->json(['error' => 'Marca d\'água inválida'], 400);
            }

            $watermarkExt = strtolower($watermark->getClientOriginalExtension()) ?: 'png';
            $watermarkName = 'watermark_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $watermarkExt;
            $watermark->move($uploadsDir, $watermarkName);
            $updates['watermark_url'] = '/uploads/tenants/' . $tenant->id . '/' . $watermarkName;
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
        $validator = Validator::make($request->all(), [
            'sistema_nome' => 'nullable|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'endereco' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string', // base64 ou URL
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

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
     * Atualizar chaves de API
     * PUT /api/admin/settings/api-keys
     * 
     * @deprecated As chaves de API agora são gerenciadas via variáveis de ambiente (.env)
     */
    public function updateApiKeys(Request $request)
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => 'As configurações de API agora são gerenciadas via variáveis de ambiente. Entre em contato com o desenvolvedor para atualizar estas configurações.',
        ], 403);
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
     * 
     * @deprecated As configurações de SMTP agora são gerenciadas via variáveis de ambiente (.env)
     */
    public function updateEmailSettings(Request $request)
    {
        return response()->json([
            'error' => 'Forbidden',
            'message' => 'As configurações de SMTP agora são gerenciadas via variáveis de ambiente. Entre em contato com o desenvolvedor para atualizar estas configurações.',
        ], 403);
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

        $validator = Validator::make($request->all(), [
            'prompt' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

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

        $validator = Validator::make($request->all(), [
            'ativo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

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

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

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

    /**
     * Atualizar configurações OpenAI
     * PUT /api/admin/settings/openai
     */
    public function updateOpenAI(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'model' => 'nullable|string',
            'assistant_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $tenant = Tenant::find($user->tenant_id);
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant->update([
            'openai_api_key' => $request->input('api_key'),
            'openai_model' => $request->input('model', 'gpt-4o-mini'),
            'ai_assistant_name' => $request->input('assistant_name', 'Assistente Virtual'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configurações OpenAI atualizadas com sucesso',
        ]);
    }

    /**
     * Atualizar configurações Twilio
     * PUT /api/admin/settings/twilio
     */
    public function updateTwilio(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $validator = Validator::make($request->all(), [
            'account_sid' => 'required|string',
            'auth_token' => 'required|string',
            'whatsapp_from' => 'required|string',
            'template_welcome_sid' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $tenant = Tenant::find($user->tenant_id);
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant->update([
            'twilio_account_sid' => $request->input('account_sid'),
            'twilio_auth_token' => $request->input('auth_token'),
            'twilio_whatsapp_from' => $request->input('whatsapp_from'),
            'twilio_template_welcome_sid' => $request->input('template_welcome_sid'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configurações Twilio atualizadas com sucesso',
        ]);
    }

    /**
     * Atualizar configurações de Email/SMTP
     * PUT /api/admin/settings/email
     */
    public function updateEmail(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $validator = Validator::make($request->all(), [
            'driver' => 'nullable|string|in:smtp,sendmail,mailgun,ses',
            'host' => 'required_if:driver,smtp|nullable|string',
            'port' => 'required_if:driver,smtp|nullable|integer',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'encryption' => 'nullable|string|in:tls,ssl',
            'from_address' => 'required|email',
            'from_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $tenant = Tenant::find($user->tenant_id);
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $tenant->update([
            'mail_driver' => $request->input('driver', 'smtp'),
            'mail_host' => $request->input('host'),
            'mail_port' => $request->input('port', 587),
            'mail_username' => $request->input('username'),
            'mail_password' => $request->input('password'),
            'mail_encryption' => $request->input('encryption', 'tls'),
            'mail_from_address' => $request->input('from_address'),
            'mail_from_name' => $request->input('from_name'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configurações de Email atualizadas com sucesso',
        ]);
    }

    /**
     * Atualizar configurações de Pagamento
     * PUT /api/admin/settings/payment
     */
    public function updatePayment(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User not authenticated or has no tenant'], 401);
        }

        $validator = Validator::make($request->all(), [
            'gateway' => 'required|string|in:mercadopago,pagarme,stripe',
            'api_key' => 'required|string',
            'api_secret' => 'nullable|string',
            'webhook_secret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'messages' => $validator->errors()], 422);
        }

        $tenant = Tenant::find($user->tenant_id);
        
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Store in metadata for flexibility
        $metadata = $tenant->metadata ?? [];
        $metadata['payment_gateway'] = $request->input('gateway');
        $metadata['payment_api_key'] = $request->input('api_key');
        
        if ($request->filled('api_secret')) {
            $metadata['payment_api_secret'] = $request->input('api_secret');
        }
        
        if ($request->filled('webhook_secret')) {
            $metadata['payment_webhook_secret'] = $request->input('webhook_secret');
        }

        $tenant->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => 'Configurações de Pagamento atualizadas com sucesso',
        ]);
    }
}

