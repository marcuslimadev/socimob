# 🔧 SOLUÇÃO: Erro 405 Method Not Allowed - Webhook WhatsApp

## ❌ Problema
```
Oops! An Error Occurred
The server returned a "405 Method Not Allowed".
URL: https://exclusivalarimoveis.com/webhook/whatsapp
```

## ✅ Correções Implementadas

### 1. **Adicionada rota GET para validação**
O Twilio pode fazer requisições GET para validar o webhook antes de usar.

**Arquivo**: [`routes/web.php`](routes/web.php#L730-L736)
```php
$router->group(['prefix' => 'webhook'], function () use ($router) {
    // GET para validação do webhook (Twilio)
    $router->get('/whatsapp', 'WebhookController@validate');
    // POST para receber mensagens
    $router->post('/whatsapp', 'WebhookController@receive');
    $router->post('/whatsapp/status', 'WebhookController@status');
});
```

### 2. **Método de validação no Controller**
**Arquivo**: [`app/Http/Controllers/WebhookController.php`](app/Http/Controllers/WebhookController.php#L26-L41)
```php
/**
 * Validar webhook (responde a requisições GET do Twilio)
 * GET /webhook/whatsapp
 */
public function validate(Request $request)
{
    Log::info('Webhook WhatsApp - Validação GET recebida', [
        'params' => $request->all(),
        'headers' => $request->headers->all()
    ]);
    
    return response('OK', 200)
        ->header('Content-Type', 'text/plain');
}
```

---

## 🧪 Testes

### Teste Local
```bash
# Dentro da pasta do projeto
php test_webhook_prod.php
```

### Teste Manual GET
```bash
curl -X GET https://exclusivalarimoveis.com/webhook/whatsapp
# Deve retornar: OK (200)
```

### Teste Manual POST
```bash
curl -X POST https://exclusivalarimoveis.com/webhook/whatsapp \
  -d "From=whatsapp:+5521999999999" \
  -d "Body=teste" \
  -d "MessageSid=TEST123"
# Deve retornar: (200 OK vazio)
```

---

## 🚀 Deploy em Produção

### Opção 1: Upload direto via FTP/cPanel
1. Fazer upload dos arquivos alterados:
   - `routes/web.php`
   - `app/Http/Controllers/WebhookController.php`

2. **Limpar cache** (importante!):
```bash
# Via SSH
cd /var/www/html/exclusivalarimoveis.com
php artisan route:clear
php artisan cache:clear

# Ou simplesmente deletar:
rm -rf bootstrap/cache/*.php
```

### Opção 2: Git Push
```bash
git add routes/web.php app/Http/Controllers/WebhookController.php
git commit -m "fix: Adiciona suporte GET para validação webhook WhatsApp"
git push origin main

# No servidor
git pull
php artisan route:clear
php artisan cache:clear
```

### Opção 3: Script de Deploy Automático
```bash
# No servidor, criar script deploy.sh
#!/bin/bash
cd /var/www/html/exclusivalarimoveis.com
git pull
composer install --no-dev
php artisan route:clear
php artisan cache:clear
chmod -R 775 storage bootstrap/cache
```

---

## 🔍 Diagnóstico Adicional

### Verificar logs do servidor
```bash
# Apache
tail -f /var/log/apache2/error.log

# Nginx
tail -f /var/log/nginx/error.log

# Lumen/Laravel
tail -f storage/logs/lumen-*.log
```

### Verificar .htaccess em produção
**Arquivo**: `public/.htaccess`
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Redirect Trailing Slashes
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    
    # Send Requests To Front Controller
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Verificar permissões
```bash
# Deve ser 775 ou 755
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Owner deve ser o usuário do servidor web (www-data, apache, nginx)
chown -R www-data:www-data storage bootstrap/cache
```

---

## 📝 Configurar Twilio Console

1. Acesse: https://console.twilio.com/us1/develop/sms/settings/whatsapp-sandbox
2. Em **"When a message comes in"**:
   - URL: `https://exclusivalarimoveis.com/webhook/whatsapp`
   - Método: **POST**
3. Em **"Status callback URL"** (opcional):
   - URL: `https://exclusivalarimoveis.com/webhook/whatsapp/status`
   - Método: **POST**
4. Clique em **Save**

---

## ⚠️ Possíveis Causas do Erro 405

### 1. **Rota não aceita o método HTTP**
- ✅ **Corrigido**: Adicionado GET + POST

### 2. **Cache de rotas**
- Solução: `php artisan route:clear`

### 3. **Problema no .htaccess**
- Verificar se `RewriteEngine On` está ativo
- Verificar se mod_rewrite está instalado: `apache2ctl -M | grep rewrite`

### 4. **Firewall/WAF bloqueando**
- Cloudflare: Verificar regras WAF
- cPanel: Verificar ModSecurity

### 5. **Servidor não suporta método**
```apache
# Adicionar ao .htaccess se necessário
<Limit GET POST>
    Order allow,deny
    Allow from all
</Limit>
```

---

## ✅ Checklist Pós-Deploy

- [ ] Fazer upload dos arquivos alterados
- [ ] Limpar cache (`php artisan route:clear`)
- [ ] Testar GET: `curl https://exclusivalarimoveis.com/webhook/whatsapp`
- [ ] Testar POST: `curl -X POST https://exclusivalarimoveis.com/webhook/whatsapp -d "test=1"`
- [ ] Verificar logs: `tail -f storage/logs/lumen-*.log`
- [ ] Atualizar URL no Twilio Console
- [ ] Enviar mensagem de teste via WhatsApp
- [ ] Verificar se mensagem aparece no sistema

---

## 📞 Suporte

Se o erro persistir após seguir todos os passos:

1. **Coletar informações**:
   ```bash
   curl -v https://exclusivalarimoveis.com/webhook/whatsapp
   cat storage/logs/lumen-$(date +%Y-%m-%d).log
   ```

2. **Verificar configuração do servidor**:
   - Apache: `apache2ctl -M`
   - PHP: `php -v`
   - Lumen: `cat bootstrap/app.php | grep routeMiddleware`

3. **Contatar provedor de hospedagem** se:
   - mod_rewrite não estiver ativo
   - AllowOverride não estiver como All
   - Houver firewall/WAF bloqueando

---

**Data**: 24/12/2025  
**Status**: ✅ Correções implementadas, aguardando deploy
