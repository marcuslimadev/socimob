# 🚀 Deploy Automático via Webhook

## 📋 Visão Geral
Endpoint para deploy automático executando comandos Git + Composer no servidor via HTTP.

## 🔐 Segurança
- **Autenticação**: Token secreto obrigatório
- **Validação**: Hash comparison (timing-attack safe)
- **Logs**: Todas as requisições são registradas

## ⚙️ Configuração

### 1. Variáveis de Ambiente (.env)
```bash
# Token secreto para deploy (ALTERAR EM PRODUÇÃO!)
DEPLOY_SECRET=seu-token-super-secreto-aqui

# Paths dos projetos
DEPLOY_PATH_LOJA=/home/usuario/domains/lojadaesquina.store/public_html
DEPLOY_PATH_EXCLUSIVA=/home/usuario/domains/exclusivalarimoveis.com/public_html

# Paths personalizados (opcional)
PHP_PATH=/opt/alt/php83/usr/bin/php
COMPOSER_PATH=/usr/local/bin/composer
```

### 2. Gerar Token Seguro
```bash
# Linux/Mac
openssl rand -hex 32

# PowerShell (Windows)
[Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Minimum 0 -Maximum 256 }))
```

## 🌐 Endpoints

### POST ou GET /api/deploy
Executa deploy completo. **Ambos os métodos funcionam!**

**Via GET (navegador):**
```
https://seudominio.com/api/deploy?secret=seu-token-secreto&project=lojadaesquina
```

**Via POST (programático):**
```
X-Deploy-Secret: seu-token-secreto
```

**Body (JSON - opcional):**
```json
{
  "project": "lojadaesquina"
}
```

**Projetos disponíveis:**
- `lojadaesquina` - Loja da Esquina
- `exclusiva` - Exclusiva Lar Imóveis
- `default` - Projeto atual (base_path)

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "message": "Deploy realizado com sucesso",
  "project": "lojadaesquina",
  "duration": "2.35s",
  "errors": [],
  "output": {
    "git_pull": {
      "command": "cd /home/... && git pull 2>&1",
      "output": ["Already up to date."],
      "exit_code": 0
    },
    "composer_install": {
      "command": "cd /home/... && php composer install ...",
      "output": ["Loading composer repositories..."],
      "exit_code": 0
    },
    "cache_clear": { ... },
    "permissions": { ... }
  },
  "timestamp": "2025-12-24 15:30:45"
}
```

**Resposta de Erro (401):**
```json
{
  "success": false,
  "message": "Unauthorized: Invalid deploy secret"
}
```

### GET /api/deploy/info
Informações do sistema (útil para debug).

**Headers:**
```
X-Deploy-Secret: seu-token-secreto
```

**Resposta:**
```json
{
  "php_version": "8.3.0",
  "php_path": "/opt/alt/php83/usr/bin/php",
  "composer_path": "/usr/local/bin/composer",
  "base_path": "/home/usuario/public_html",
  "server": {
    "os": "Linux",
    "server_software": "Apache/2.4.58",
    "user": "usuario"
  },
  "git": {
    "available": true,
    "version": "git version 2.40.0"
  }
}
```

## 🧪 Testes

### Interface Web (Mais Fácil!)
Acesse no navegador:
```
http://127.0.0.1:8000/deploy.html
```

Interface visual com:
- ✅ Seleção de projeto
- ✅ Log em tempo real estilo terminal
- ✅ Informações do sistema
- ✅ Um clique para deploy!

### Teste Local (PowerShell)
```powershell
.\test_deploy.ps1
```

### Teste Manual (cURL)
```bash
# Teste de deploy
curl -X POST https://lojadaesquina.store/api/deploy \
  -H "X-Deploy-Secret: seu-token-secreto" \
  -H "Content-Type: application/json" \
  -d '{"project":"lojadaesquina"}'

# Info do sistema
curl -X GET https://lojadaesquina.store/api/deploy/info \
  -H "X-Deploy-Secret: seu-token-secreto"
```

### Teste Manual (PowerShell)
```powershell
$headers = @{
    "X-Deploy-Secret" = "seu-token-secreto"
    "Content-Type" = "application/json"
}

$body = @{
    project = "lojadaesquina"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://lojadaesquina.store/api/deploy" `
    -Method POST `
    -Headers $headers `
    -Body $body
```

## 🔗 Integração com Git Providers

### GitHub Actions
```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger Deploy Webhook
        run: |
          curl -X POST https://lojadaesquina.store/api/deploy \
            -H "X-Deploy-Secret: ${{ secrets.DEPLOY_SECRET }}" \
            -H "Content-Type: application/json" \
            -d '{"project":"lojadaesquina"}'
```

**Configurar Secret no GitHub:**
1. Repositório → Settings → Secrets and variables → Actions
2. New repository secret: `DEPLOY_SECRET`
3. Colar o token

### GitLab CI/CD
```yaml
deploy:
  stage: deploy
  only:
    - main
  script:
    - |
      curl -X POST https://lojadaesquina.store/api/deploy \
        -H "X-Deploy-Secret: $DEPLOY_SECRET" \
        -H "Content-Type: application/json" \
        -d '{"project":"lojadaesquina"}'
  variables:
    DEPLOY_SECRET: $DEPLOY_SECRET # Configurar em CI/CD Settings
```

### Bitbucket Pipelines
```yaml
pipelines:
  branches:
    main:
      - step:
          name: Deploy
          script:
            - |
              curl -X POST https://lojadaesquina.store/api/deploy \
                -H "X-Deploy-Secret: $DEPLOY_SECRET" \
                -H "Content-Type: application/json" \
                -d '{"project":"lojadaesquina"}'
```

## 📊 O que o Endpoint Faz

### 1. Git Pull
```bash
cd /home/usuario/domains/lojadaesquina.store/public_html
git pull
```

### 2. Composer Install
```bash
/opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

### 3. Artisan Commands (com PHP path completo)
```bash
/opt/alt/php83/usr/bin/php artisan route:clear
/opt/alt/php83/usr/bin/php artisan cache:clear
/opt/alt/php83/usr/bin/php artisan config:clear
```

### 4. Ajustar Permissões
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## 🔍 Logs

Todos os deploys são registrados em:
```
storage/logs/lumen-YYYY-MM-DD.log
```

**Exemplo de log:**
```
[2025-12-24 15:30:45] local.INFO: ═══════════════════════════════════════════════
[2025-12-24 15:30:45] local.INFO: 🚀 DEPLOY WEBHOOK RECEBIDO
[2025-12-24 15:30:45] local.INFO: ═══════════════════════════════════════════════
[2025-12-24 15:30:45] local.INFO: 📦 Projeto: lojadaesquina
[2025-12-24 15:30:45] local.INFO: 📁 Diretório: /home/usuario/domains/...
[2025-12-24 15:30:45] local.INFO: 🔄 Executando git pull...
[2025-12-24 15:30:46] local.INFO: ✅ Git pull concluído {"output":"Already up to date."}
[2025-12-24 15:30:46] local.INFO: 📦 Executando composer install...
[2025-12-24 15:30:48] local.INFO: ✅ Composer install concluído
[2025-12-24 15:30:48] local.INFO: ✅ DEPLOY CONCLUÍDO COM SUCESSO
```

## ⚠️ Considerações de Segurança

### 1. **Mantenha o Token Secreto Seguro**
- ❌ Nunca commitar no Git
- ❌ Nunca enviar em URLs (query params)
- ✅ Usar variáveis de ambiente
- ✅ Usar secrets do CI/CD

### 2. **Permissões do Servidor**
- Usuário web deve ter acesso ao diretório Git
- SSH keys configuradas para `git pull`
- Composer instalado e acessível

### 3. **Rate Limiting (Recomendado)**
Adicionar middleware para limitar requisições:
```php
// Em bootstrap/app.php
$app->routeMiddleware([
    'throttle' => App\Http\Middleware\ThrottleRequests::class,
]);

// Em routes/web.php
$router->group(['middleware' => 'throttle:5,1'], function () use ($router) {
    $router->post('/api/deploy', 'DeployController@deploy');
});
```

### 4. **IP Whitelist (Opcional)**
Permitir apenas IPs conhecidos:
```php
// No DeployController
private $allowedIps = [
    '192.30.252.0/22', // GitHub Actions
    '185.199.108.0/22', // GitHub Pages
    // Adicionar IPs do seu CI/CD
];
```

## 🆘 Troubleshooting

### "Unauthorized: Invalid deploy secret"
- Verificar se `DEPLOY_SECRET` está no .env
- Verificar se o header `X-Deploy-Secret` está correto
- Verificar se não há espaços extras no token

### "Git pull falhou"
- Verificar se o diretório existe
- Verificar permissões do usuário web
- Verificar se SSH keys estão configuradas
- Testar manualmente: `sudo -u www-data git pull`

### "Composer install falhou"
- Verificar se Composer está instalado
- Verificar path correto: `which composer`
- Verificar PHP path: `which php`
- Testar manualmente: `/opt/alt/php83/usr/bin/php /usr/local/bin/composer --version`

### "Permission denied"
```bash
# Ajustar owner (no servidor)
chown -R usuario:usuario /home/usuario/domains/lojadaesquina.store/public_html

# Ajustar permissões
chmod -R 775 storage bootstrap/cache
```

## 📚 Referências

- [GitHub Actions Webhooks](https://docs.github.com/en/actions)
- [GitLab CI/CD](https://docs.gitlab.com/ee/ci/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Git Documentation](https://git-scm.com/doc)

---

**Criado em**: 24/12/2025  
**Última atualização**: 24/12/2025
