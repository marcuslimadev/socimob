# 🟢 GUIA COMPLETO: Teste Webhook WhatsApp via Ngrok

## 📋 Pré-requisitos

1. ✅ Servidor PHP rodando na porta 8000
2. ✅ Ngrok configurado e rodando
3. ✅ URL do ngrok atualizada no script de teste

## 🚀 Passo a Passo

### 1️⃣ Iniciar Servidor Local

```powershell
# Parar processos PHP existentes (se houver)
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force

# Iniciar servidor na pasta do projeto
cd c:\Projetos\saas
php -S 127.0.0.1:8000 -t public
```

Você deve ver:
```
[Tue Dec 23 XX:XX:XX 2025] PHP 8.2.12 Development Server (http://127.0.0.1:8000) started
```

### 2️⃣ Iniciar Ngrok (em outro terminal)

```powershell
# Navegar até a pasta do ngrok
cd C:\caminho\para\ngrok

# Iniciar túnel para porta 8000
ngrok http 8000
```

Você verá algo como:
```
Forwarding  https://XXXXXXXXXXXX.ngrok-free.app -> http://localhost:8000
```

**⚠️ IMPORTANTE:** Copie a URL do ngrok (ex: `https://99a3345711a3.ngrok-free.app`)

### 3️⃣ Atualizar URL no Script

Edite `test_webhook_ngrok.ps1` e atualize a linha 11:

```powershell
$ngrokUrl = "https://SUA_URL_AQUI.ngrok-free.app"
```

### 4️⃣ Executar Teste

```powershell
# Em um NOVO terminal PowerShell
cd c:\Projetos\saas
.\test_webhook_ngrok.ps1
```

## 🔍 Verificar Resultados

### ✅ Teste Local (Sem Ngrok)

Antes de testar via ngrok, confirme que o endpoint funciona localmente:

```powershell
$body = @{
    From = "whatsapp:+5521987654321"
    To = "whatsapp:+5521999887766"
    Body = "Teste local"
    MessageSid = "SM123456"
    ProfileName = "Teste"
} | ConvertTo-Json

Invoke-WebRequest -Uri "http://127.0.0.1:8000/webhook/whatsapp" `
    -Method POST `
    -Body $body `
    -ContentType "application/json" `
    -UseBasicParsing
```

Resposta esperada: `200 OK`

### 🌐 Teste via Navegador

Abra: `http://127.0.0.1:8000/test-webhook-whatsapp.html`

1. Atualize a URL do ngrok no campo
2. Clique em "Enviar Mensagem Twilio"
3. Verifique a resposta no painel de logs

### 📝 Verificar Logs da Aplicação

```powershell
# Ver últimas linhas do log
Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Tail 50
```

Procure por:
```
╔═══════════════════════════════════════════════════════════════════╗
║           📥 WEBHOOK RECEBIDO - TWILIO                           ║
╚═══════════════════════════════════════════════════════════════════╝
```

## 🐛 Troubleshooting

### ❌ Erro 404 no Ngrok

**Problema:** Ngrok retorna 404 Not Found

**Soluções:**

1. **Verificar se servidor está rodando:**
   ```powershell
   Get-Process php
   ```

2. **Testar endpoint local primeiro:**
   ```powershell
   Invoke-WebRequest http://127.0.0.1:8000 -UseBasicParsing
   ```
   Deve retornar JSON da aplicação

3. **Verificar rota no Lumen:**
   ```php
   // routes/web.php deve ter:
   $router->group(['prefix' => 'webhook'], function () use ($router) {
       $router->post('/whatsapp', 'WebhookController@receive');
   });
   ```

4. **Testar com curl simples:**
   ```powershell
   curl -X POST http://127.0.0.1:8000/webhook/whatsapp -d "test=1"
   ```

### ❌ Ngrok "Connection Refused"

**Problema:** Ngrok não consegue conectar ao localhost

**Solução:**
```powershell
# Reiniciar servidor PHP
Get-Process php | Stop-Process -Force
php -S 127.0.0.1:8000 -t public
```

### ❌ CORS Error

**Problema:** Erro de CORS ao testar no navegador

**Solução:** Isso é normal para testes locais. Use o script PowerShell ou teste via Postman/Insomnia.

### ❌ Webhook não processa

**Problema:** Webhook recebe mas não processa mensagem

**Verificar:**

1. **Tenant está configurado:**
   ```sql
   SELECT id, name, domain FROM tenants;
   ```

2. **WhatsApp Service está carregado:**
   Verifique logs para erros no `WhatsAppService`

3. **TenantConfig tem configurações WhatsApp:**
   ```sql
   SELECT tenant_id, twilio_whatsapp_from, twilio_account_sid 
   FROM tenant_configs;
   ```

## 📱 Configurar Webhook Real

### Para Twilio:

1. Acesse: https://console.twilio.com/
2. Vá em: Messaging → WhatsApp → Sandbox
3. Configure:
   - **When a message comes in**: `https://SUA_URL.ngrok-free.app/webhook/whatsapp`
   - **HTTP Method**: POST

### Para Evolution API:

1. Acesse painel da Evolution API
2. Configure webhook:
   ```json
   {
     "url": "https://SUA_URL.ngrok-free.app/webhook/whatsapp",
     "events": ["messages.upsert"]
   }
   ```

## 📊 Monitorar Requisições

### Via Ngrok Web Interface:

Abra: `http://127.0.0.1:4040`

Você verá:
- Todas as requisições recebidas
- Headers
- Body
- Resposta do servidor

Isso é MUITO útil para debug!

### Via Logs Lumen:

```powershell
# Acompanhar logs em tempo real
Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Wait -Tail 20
```

## ✨ Testes Automatizados

### Teste Completo (PowerShell):
```powershell
.\test_webhook_ngrok.ps1
```

### Teste Individual (curl):
```bash
curl -X POST https://SUA_URL.ngrok-free.app/webhook/whatsapp \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "From=whatsapp:+5521987654321" \
  -d "To=whatsapp:+5521999887766" \
  -d "Body=Olá teste" \
  -d "MessageSid=SM123456" \
  -d "ProfileName=Teste User"
```

### Teste via Browser:
```
http://127.0.0.1:8000/test-webhook-whatsapp.html
```

## 🎯 Checklist Completo

- [ ] Servidor PHP rodando (`php -S 127.0.0.1:8000 -t public`)
- [ ] Ngrok rodando (`ngrok http 8000`)
- [ ] URL do ngrok copiada
- [ ] Script atualizado com nova URL
- [ ] Teste local funcionando (200 OK)
- [ ] Teste via ngrok funcionando (200 OK)
- [ ] Logs mostrando webhook recebido
- [ ] Mensagem sendo processada corretamente
- [ ] Resposta sendo enviada (se aplicável)

## 📞 Suporte

Se ainda tiver problemas:

1. **Verifique logs**: `storage/logs/lumen-YYYY-MM-DD.log`
2. **Verifique ngrok dashboard**: `http://127.0.0.1:4040`
3. **Teste endpoint local primeiro** antes de usar ngrok
4. **Verifique banco de dados**: Tenant e configs existem?

---

**Última atualização:** 23/12/2025
