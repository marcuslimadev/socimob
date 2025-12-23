# 📱 CONFIGURAR WHATSAPP REAL COM NGROK

## 🎯 Objetivo
Receber mensagens REAIS do seu WhatsApp no sistema via webhook ngrok

## 📋 Pré-requisitos
- ✅ Servidor rodando: `php -S 127.0.0.1:8000 -t public`
- ✅ Ngrok rodando: `ngrok http 8000`
- ✅ URL do ngrok: `https://99a3345711a3.ngrok-free.app`

---

## 🟢 OPÇÃO 1: Twilio Sandbox (MAIS RÁPIDO - GRÁTIS)

### Passo 1: Acessar Twilio Console
1. Vá em: https://console.twilio.com/
2. Login com sua conta

### Passo 2: Configurar WhatsApp Sandbox
1. No menu lateral: **Messaging** → **Try it out** → **Send a WhatsApp message**
2. Ou acesse direto: https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn

### Passo 3: Conectar seu WhatsApp
Você verá algo como:

```
Join your sandbox by sending:
join [código-único]
to: +1 415 523 8886
```

**Ação:**
1. Abra seu WhatsApp
2. Adicione o número mostrado (ex: +1 415 523 8886)
3. Envie a mensagem: `join [seu-código]`
4. Você receberá confirmação: "You are all set!"

### Passo 4: Configurar Webhook no Twilio

1. No Twilio Console, vá em: **Messaging** → **Settings** → **WhatsApp sandbox settings**
2. Em **"When a message comes in"**, cole:
   ```
   https://99a3345711a3.ngrok-free.app/webhook/whatsapp
   ```
3. Método: **POST**
4. Clique em **Save**

### Passo 5: TESTAR! 🚀

Agora simplesmente:
1. Abra seu WhatsApp
2. Envie qualquer mensagem para o número do Twilio
3. **A mensagem vai aparecer no sistema!** ✨

---

## 🔵 OPÇÃO 2: Evolution API (Se você já tem instalada)

### Passo 1: Acessar Evolution API
```
http://SEU_IP:8080
```

### Passo 2: Conectar WhatsApp
1. Crie uma instância
2. Escaneie o QR Code com seu WhatsApp
3. Aguarde conexão

### Passo 3: Configurar Webhook
Via API ou interface:

```bash
curl -X POST 'http://SEU_IP:8080/webhook/set/[INSTANCE_NAME]' \
  -H 'Content-Type: application/json' \
  -d '{
    "url": "https://99a3345711a3.ngrok-free.app/webhook/whatsapp",
    "webhook_by_events": false,
    "events": ["messages.upsert"]
  }'
```

### Passo 4: TESTAR! 🚀
1. Qualquer mensagem recebida no WhatsApp conectado
2. Será enviada para o webhook
3. Aparecerá no sistema!

---

## 🔍 MONITORAR MENSAGENS

### Ver Logs em Tempo Real
```powershell
Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Wait -Tail 20
```

Você verá algo como:
```
╔═══════════════════════════════════════════════════════════════════╗
║           📥 WEBHOOK RECEBIDO - TWILIO                           ║
╚═══════════════════════════════════════════════════════════════════╝
📞 De: +5521987654321
👤 Nome: João da Silva
💬 Mensagem: Olá! Quero saber sobre imóveis
```

### Ngrok Dashboard
Abra: `http://127.0.0.1:4040`

Aqui você vê TUDO:
- Todas as requisições HTTP
- Headers completos
- Body (payload) das mensagens
- Resposta do servidor

---

## 📊 VERIFICAR SE ESTÁ FUNCIONANDO

### Teste Rápido
```powershell
# Envie uma mensagem do seu WhatsApp para o número Twilio
# Depois rode:
Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Tail 30
```

### Você deve ver:
- ✅ `WEBHOOK RECEBIDO`
- ✅ Dados da mensagem (From, Body, ProfileName)
- ✅ `WEBHOOK PROCESSADO COM SUCESSO`

### Verificar no Banco
```sql
-- Ver conversas criadas
SELECT * FROM conversations ORDER BY created_at DESC LIMIT 5;

-- Ver mensagens recebidas
SELECT * FROM messages ORDER BY created_at DESC LIMIT 10;

-- Ver leads criados
SELECT * FROM leads ORDER BY created_at DESC LIMIT 5;
```

---

## 🎯 FLUXO COMPLETO

```
SEU WHATSAPP
    ↓
[Envia mensagem]
    ↓
TWILIO/EVOLUTION
    ↓
[Recebe e formata]
    ↓
NGROK (https://99a3345711a3.ngrok-free.app)
    ↓
[Túnel para localhost]
    ↓
SERVIDOR LOCAL (127.0.0.1:8000)
    ↓
[Rota: /webhook/whatsapp]
    ↓
WebhookController@receive
    ↓
WhatsAppService->processIncomingMessage()
    ↓
[Cria/Atualiza: Lead, Conversation, Message]
    ↓
[Opcional: Resposta automática via IA]
    ↓
RESPONDE VIA TWILIO/EVOLUTION
    ↓
SEU WHATSAPP
```

---

## ⚠️ IMPORTANTE

### URL do Ngrok muda!
Toda vez que você reiniciar o ngrok, a URL muda. Você precisa:
1. Copiar nova URL
2. Atualizar no Twilio/Evolution
3. Atualizar nos scripts de teste

### Conta Twilio Gratuita
- ✅ Funciona perfeitamente para testes
- ⚠️ Mensagens têm prefixo "Sent from your Twilio trial account"
- ⚠️ Só pode enviar para números verificados
- 💰 Para produção: upgrade para conta paga

### Ngrok Gratuito
- ✅ Funciona perfeitamente
- ⚠️ URL muda a cada reinicialização
- ⚠️ Limite de 40 conexões/minuto
- 💰 Para produção: use domínio fixo (ngrok pago ou servidor público)

---

## 🚀 COMANDOS RÁPIDOS

### Iniciar Tudo
```powershell
# Terminal 1: Servidor
cd c:\Projetos\saas
php -S 127.0.0.1:8000 -t public

# Terminal 2: Ngrok (se tiver instalado)
ngrok http 8000

# Terminal 3: Logs
Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Wait
```

### Testar Conexão
```powershell
# 1. Testar servidor local
Invoke-WebRequest http://127.0.0.1:8000 -UseBasicParsing

# 2. Testar ngrok
Invoke-WebRequest https://99a3345711a3.ngrok-free.app -UseBasicParsing

# 3. Enviar mensagem teste
.\test_webhook_ngrok.ps1
```

---

## 📱 PRIMEIRO TESTE

**Agora faça isso:**

1. ✅ Confirme que servidor está rodando
2. ✅ Confirme que ngrok está rodando
3. ✅ Configure webhook no Twilio (Opção 1 acima)
4. ✅ Conecte seu WhatsApp ao sandbox Twilio
5. ✅ Abra terminal com logs:
   ```powershell
   Get-Content "storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log" -Wait
   ```
6. 🚀 **Envie mensagem do seu WhatsApp**
7. 👀 **Veja a mágica acontecer nos logs!**

---

**Boa sorte! 🎉**

Qualquer mensagem que você enviar vai:
- ✅ Aparecer nos logs
- ✅ Criar um Lead no sistema
- ✅ Criar uma Conversation
- ✅ Salvar a Message
- ✅ (Opcional) Gerar resposta automática com IA
