<?php

/**
 * Seeder completo para a Imobiliária Exclusiva
 * Cria tenant, super admin e usuários do sistema
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Laravel\Lumen\Bootstrap\LoadEnvironmentVariables;

// Carregar ambiente
(new LoadEnvironmentVariables(dirname(__DIR__, 2)))->bootstrap();

try {
    // Conexão com banco
    $pdo = new PDO(
        'mysql:host=' . env('DB_HOST', 'localhost') . ';dbname=' . env('DB_DATABASE', 'exclusiva'),
        env('DB_USERNAME', 'root'),
        env('DB_PASSWORD', ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "🌱 Iniciando seed da Imobiliária Exclusiva...\n\n";

    // ============================================================================
    // 1. CRIAR TENANT EXCLUSIVA
    // ============================================================================
    
    $exclusivaTenantData = [
        'name' => 'Exclusiva Imóveis',
        'domain' => 'exclusiva.localhost',
        'slug' => 'exclusiva',
        'theme' => 'default',
        'primary_color' => '#1f2937',
        'secondary_color' => '#3b82f6',
        'logo_url' => '/assets/logo-exclusiva.png',
        'favicon_url' => '/assets/favicon.ico',
        'slogan' => 'Seu imóvel dos sonhos está aqui',
        'subscription_status' => 'active',
        'subscription_plan' => 'premium',
        'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
        'subscription_started_at' => date('Y-m-d H:i:s'),
        'contact_email' => 'contato@exclusiva.com.br',
        'contact_phone' => '(11) 99999-9999',
        'description' => 'Imobiliária líder no mercado com mais de 10 anos de experiência',
        'is_active' => 1,
        'max_users' => 50,
        'max_properties' => 10000,
        'max_leads' => 50000,
        'api_token' => bin2hex(random_bytes(32)),
        'metadata' => json_encode([
            'features' => ['crm', 'whatsapp', 'portal', 'analytics'],
            'integrations' => ['apm_imoveis', 'neca'],
            'created_by' => 'seed'
        ])
    ];

    // Verificar se tenant já existe
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
    $stmt->execute(['exclusiva']);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        $columns = implode(', ', array_keys($exclusivaTenantData));
        $placeholders = ':' . implode(', :', array_keys($exclusivaTenantData));
        
        $stmt = $pdo->prepare("INSERT INTO tenants ({$columns}, created_at, updated_at) VALUES ({$placeholders}, NOW(), NOW())");
        $stmt->execute($exclusivaTenantData);
        $tenantId = $pdo->lastInsertId();
        echo "✅ Tenant Exclusiva criado (ID: {$tenantId})\n";
    } else {
        $tenantId = $tenant['id'];
        echo "ℹ️  Tenant Exclusiva já existe (ID: {$tenantId})\n";
    }

    // ============================================================================
    // 2. CRIAR SUPER ADMIN
    // ============================================================================
    
    $superAdminData = [
        'name' => 'Super Administrador',
        'email' => 'admin@exclusiva.com',
        'password' => password_hash('password', PASSWORD_BCRYPT),
        'role' => 'super_admin',
        'is_active' => 1,
        'tenant_id' => null // Super admin não pertence a tenant específico
    ];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$superAdminData['email']]);
    $user = $stmt->fetch();

    if (!$user) {
        $columns = implode(', ', array_keys($superAdminData));
        $placeholders = ':' . implode(', :', array_keys($superAdminData));
        
        $stmt = $pdo->prepare("INSERT INTO users ({$columns}, created_at, updated_at) VALUES ({$placeholders}, NOW(), NOW())");
        $stmt->execute($superAdminData);
        echo "✅ Super Admin criado: {$superAdminData['email']} / password\n";
    } else {
        echo "ℹ️  Super Admin já existe: {$superAdminData['email']}\n";
    }

    // ============================================================================
    // 3. CRIAR USUÁRIOS DA EXCLUSIVA
    // ============================================================================
    
    $exclusivaUsers = [
        [
            'name' => 'Contato Exclusiva',
            'email' => 'contato@exclusiva.com.br',
            'password' => password_hash('Teste@123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'is_active' => 1,
            'tenant_id' => $tenantId
        ],
        [
            'name' => 'Alexsandra Silva',
            'email' => 'alexsandra@exclusiva.com.br',
            'password' => password_hash('Senha@123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'is_active' => 1,
            'tenant_id' => $tenantId
        ],
        [
            'name' => 'Marcus Lima',
            'email' => 'marcus@exclusiva.com.br',
            'password' => password_hash('Dev@123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'is_active' => 1,
            'tenant_id' => $tenantId
        ],
        [
            'name' => 'Corretor Demo',
            'email' => 'corretor@exclusiva.com.br',
            'password' => password_hash('Corretor@123', PASSWORD_BCRYPT),
            'role' => 'agent',
            'is_active' => 1,
            'tenant_id' => $tenantId
        ]
    ];

    foreach ($exclusivaUsers as $userData) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            $columns = implode(', ', array_keys($userData));
            $placeholders = ':' . implode(', :', array_keys($userData));
            
            $stmt = $pdo->prepare("INSERT INTO users ({$columns}, created_at, updated_at) VALUES ({$placeholders}, NOW(), NOW())");
            $stmt->execute($userData);
            echo "✅ Usuário criado: {$userData['name']} ({$userData['email']}) - Role: {$userData['role']}\n";
        } else {
            echo "ℹ️  Usuário já existe: {$userData['email']}\n";
        }
    }

    // ============================================================================
    // 4. CONFIGURAÇÕES ADICIONAIS (OPCIONAL)
    // ============================================================================
    
    // Criar algumas configurações básicas se necessário
    echo "\n📋 Resumo dos dados criados:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🏢 TENANT: Exclusiva Imóveis\n";
    echo "   Domain: exclusiva.localhost\n";
    echo "   API Token: " . substr($exclusivaTenantData['api_token'], 0, 20) . "...\n\n";
    
    echo "👤 CREDENCIAIS DE ACESSO:\n";
    echo "   Super Admin: admin@exclusiva.com / password\n";
    echo "   Contato: contato@exclusiva.com.br / Teste@123\n";
    echo "   Alexsandra: alexsandra@exclusiva.com.br / Senha@123\n";
    echo "   Marcus: marcus@exclusiva.com.br / Dev@123\n";
    echo "   Corretor: corretor@exclusiva.com.br / Corretor@123\n\n";
    
    echo "🚀 URLs de acesso:\n";
    echo "   Sistema: http://127.0.0.1:8000/app/\n";
    echo "   API: http://127.0.0.1:8000/api/\n\n";
    
    echo "✅ Seed da Exclusiva finalizado com sucesso!\n";

} catch (Exception $e) {
    echo "❌ Erro ao executar seed: " . $e->getMessage() . "\n";
    exit(1);
}