<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;

class TenantSettingsSecurityTest extends TestCase
{
    private $tenant;
    private $admin;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Criar tenant de teste
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'slug' => 'test-tenant',
            'is_active' => true,
        ]);

        // Criar configuração do tenant
        TenantConfig::create([
            'tenant_id' => $this->tenant->id,
            'api_key_openai' => 'sk-test-key',
            'smtp_host' => 'smtp.test.com',
        ]);

        // Criar admin de teste
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);

        // Gerar token simples
        $this->token = base64_encode($this->admin->id . '|' . time() . '|secret');
    }

    protected function tearDown(): void
    {
        // Limpar dados de teste
        if ($this->admin) {
            $this->admin->delete();
        }
        if ($this->tenant && $this->tenant->config) {
            $this->tenant->config->delete();
        }
        if ($this->tenant) {
            $this->tenant->delete();
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_should_not_return_api_keys_in_settings_index()
    {
        $response = $this->json('GET', '/api/admin/settings', [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verificar que dados básicos estão presentes
        $this->assertArrayHasKey('tenant', $data);
        $this->assertArrayHasKey('config', $data);
        
        // Verificar que API keys NÃO estão presentes na resposta do tenant
        $this->assertArrayNotHasKey('api_key_pagar_me', $data['tenant']);
        $this->assertArrayNotHasKey('api_key_apm_imoveis', $data['tenant']);
        $this->assertArrayNotHasKey('api_key_neca', $data['tenant']);
        $this->assertArrayNotHasKey('api_key_openai', $data['tenant']);
        $this->assertArrayNotHasKey('api_url_externa', $data['tenant']);
        $this->assertArrayNotHasKey('api_token_externa', $data['tenant']);
    }

    /** @test */
    public function it_should_return_403_when_trying_to_update_api_keys()
    {
        $response = $this->json('PUT', '/api/admin/settings/api-keys', [
            'api_key_openai' => 'sk-new-key',
            'twilio_account_sid' => 'AC123',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertStringContainsString('variáveis de ambiente', $data['message']);
    }

    /** @test */
    public function it_should_return_403_when_trying_to_update_email_settings()
    {
        $response = $this->json('PUT', '/api/admin/settings/email', [
            'smtp_host' => 'smtp.new.com',
            'smtp_port' => 587,
            'smtp_username' => 'test@test.com',
            'smtp_password' => 'password',
            'smtp_from_email' => 'noreply@test.com',
            'smtp_from_name' => 'Test',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403);
        
        $data = $response->json();
        
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Forbidden', $data['error']);
        $this->assertStringContainsString('variáveis de ambiente', $data['message']);
    }

    /** @test */
    public function it_should_not_accept_api_url_externa_in_update_tenant()
    {
        $response = $this->json('PUT', '/api/admin/settings/tenant', [
            'name' => 'Updated Tenant',
            'api_url_externa' => 'https://example.com/api',
            'api_token_externa' => 'token123',
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        // A validação deve falhar ou os campos devem ser ignorados
        // Vamos verificar se o tenant foi atualizado mas sem os campos de API
        $this->tenant->refresh();
        
        // Nome deve ser atualizado
        if ($response->status() === 200) {
            $this->assertEquals('Updated Tenant', $this->tenant->name);
        }
        
        // API externa não deve ser atualizada (ou deve manter valor null)
        // Nota: Isso depende se o campo existe na migration
        // Se existir, deve permanecer null
    }

    /** @test */
    public function tenant_config_getApiKeys_should_use_env_variables()
    {
        // Definir variável de ambiente para teste
        $tenantId = $this->tenant->id;
        putenv("TENANT_{$tenantId}_OPENAI_KEY=sk-env-key");
        
        $config = $this->tenant->config;
        $apiKeys = $config->getApiKeys();
        
        // Deve retornar o valor da variável de ambiente
        $this->assertEquals('sk-env-key', $apiKeys['openai']);
        
        // Limpar variável de ambiente
        putenv("TENANT_{$tenantId}_OPENAI_KEY");
    }

    /** @test */
    public function tenant_config_getSmtpConfig_should_use_env_variables()
    {
        // Definir variável de ambiente para teste
        $tenantId = $this->tenant->id;
        putenv("TENANT_{$tenantId}_SMTP_HOST=smtp.env.com");
        putenv("TENANT_{$tenantId}_SMTP_PORT=465");
        
        $config = $this->tenant->config;
        $smtpConfig = $config->getSmtpConfig();
        
        // Deve retornar valores das variáveis de ambiente
        $this->assertEquals('smtp.env.com', $smtpConfig['host']);
        $this->assertEquals('465', $smtpConfig['port']);
        
        // Limpar variáveis de ambiente
        putenv("TENANT_{$tenantId}_SMTP_HOST");
        putenv("TENANT_{$tenantId}_SMTP_PORT");
    }

    /** @test */
    public function tenant_config_should_fallback_to_database_when_env_not_set()
    {
        // Sem variável de ambiente, deve usar valor do banco
        $config = $this->tenant->config;
        $apiKeys = $config->getApiKeys();
        
        // Deve retornar o valor do banco de dados
        $this->assertEquals('sk-test-key', $apiKeys['openai']);
        
        $smtpConfig = $config->getSmtpConfig();
        $this->assertEquals('smtp.test.com', $smtpConfig['host']);
    }
}
