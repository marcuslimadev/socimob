<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = require __DIR__ . '/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
*/

$app->run();

// Página de status desativada - Laravel/Lumen está ativo!
exit;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exclusiva SaaS - Docker Running</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .status {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item:last-child { border-bottom: none; }
        .label { font-weight: 600; color: #6b7280; }
        .value { color: #1f2937; font-family: 'Courier New', monospace; }
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        h2 {
            color: #374151;
            margin: 30px 0 15px;
            font-size: 1.5em;
        }
        .step {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Exclusiva SaaS</h1>
        <span class="status">✅ Docker Running</span>
        
        <div class="info">
            <div class="info-item">
                <span class="label">PHP Version:</span>
                <span class="value success"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Server:</span>
                <span class="value success"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Nginx'; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Document Root:</span>
                <span class="value"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></span>
            </div>
            <div class="info-item">
                <span class="label">Containers:</span>
                <span class="value success">App + MySQL + Redis</span>
            </div>
        </div>

        <h2>✅ Configurações Completas</h2>
        
        <div class="info">
            <div class="info-item">
                <span class="label">Composer:</span>
                <span class="value success">✅ Instalado (v2.9.2)</span>
            </div>
            <div class="info-item">
                <span class="label">Laravel/Lumen:</span>
                <span class="value success">✅ v10.0.4</span>
            </div>
            <div class="info-item">
                <span class="label">Migrations:</span>
                <span class="value success">✅ Executadas (5 tabelas)</span>
            </div>
            <div class="info-item">
                <span class="label">Cache/Session:</span>
                <span class="value success">✅ Database</span>
            </div>
        </div>

        <h2>📋 Tabelas Criadas</h2>
        
        <div class="step" style="background: #d1fae5; border-color: #10b981;">
            <strong>✅ tenants</strong> - Gerenciamento de imobiliárias<br>
            <strong>✅ subscriptions</strong> - Sistema de assinaturas<br>
            <strong>✅ tenant_configs</strong> - Configurações personalizadas<br>
            <strong>✅ migrations</strong> - Controle de versão do banco
        </div>
        
        <h2>🚀 Próximo Passo</h2>
        
        <div class="step">
            <strong>Criar primeiro tenant:</strong><br>
            <code>docker exec -it exclusiva-app php artisan tinker</code><br>
            <small style="color: #6b7280; display: block; margin-top: 8px;">
            Ou acesse via SQL e insira diretamente na tabela <code style="background: #e5e7eb; color: #1f2937;">tenants</code>
            </small>
        </div>

        <h2>🔗 Acessos</h2>
        <div class="info">
            <div class="info-item">
                <span class="label">Web:</span>
                <span class="value">http://localhost:8080</span>
            </div>
            <div class="info-item">
                <span class="label">MySQL:</span>
                <span class="value">localhost:3307</span>
            </div>
            <div class="info-item">
                <span class="label">Redis:</span>
                <span class="value">localhost:6379</span>
            </div>
        </div>

        <h2>🔐 Acesso Super Admin</h2>
        
        <div class="info" style="background: #fef3c7; border: 2px solid #f59e0b; padding: 20px;">
            <div class="info-item">
                <span class="label">Email:</span>
                <span class="value" style="color: #f59e0b; font-weight: bold;">admin@exclusiva.com</span>
            </div>
            <div class="info-item">
                <span class="label">Senha:</span>
                <span class="value" style="color: #f59e0b; font-weight: bold;">password</span>
            </div>
            <div class="info-item">
                <span class="label">Endpoint:</span>
                <span class="value" style="font-size: 0.85em;">POST /api/login</span>
            </div>
        </div>

        <div class="step">
            <strong>Fazer login via cURL:</strong><br>
            <code style="display: block; margin-top: 8px; word-break: break-all;">
            curl -X POST http://localhost:8080/api/login -H "Content-Type: application/json" -d "{\"email\":\"admin@exclusiva.com\",\"password\":\"password\"}"
            </code>
        </div>

        <div class="footer">
            Exclusiva SaaS Multi-Tenant Platform | Dezembro 2025
        </div>
    </div>
</body>
</html>
