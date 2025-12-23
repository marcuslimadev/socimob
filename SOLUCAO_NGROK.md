# 🚨 SOLUÇÃO RÁPIDA - Erro Ngrok ERR_NGROK_8012

## ✅ PROBLEMA RESOLVIDO!

O servidor PHP agora está configurado corretamente para funcionar com ngrok.

**Mudança importante:** Servidor agora escuta em `0.0.0.0:8000` (todas as interfaces)

---

## 🚀 COMO USAR AGORA

### 1️⃣ Servidor PHP já está rodando! ✅

Uma nova janela PowerShell foi aberta com o servidor em:
```
http://0.0.0.0:8000
```

**NÃO FECHE essa janela!** Deixe rodando.

### 2️⃣ Iniciar/Reiniciar Ngrok

O ngrok gratuito expira. Você precisa reiniciá-lo:

```bash
# Se você tem ngrok instalado:
ngrok http 8000
```

Você verá algo assim:
```
Session Status                online
Account                       Seu Nome (Plan: Free)
Forwarding                    https://XXXX-XXX-XXX-XXX.ngrok-free.app -> http://localhost:8000
```

**⚠️ COPIE A NOVA URL!** Ela muda toda vez que reinicia.

### 3️⃣ Configurar Webhook no Twilio

1. Acesse: https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn
2. Vá em **Sandbox Settings**
3. Cole a NOVA URL do ngrok:
   ```
   https://SUA-NOVA-URL.ngrok-free.app/webhook/whatsapp
   ```
4. Método: **POST**
5. Salve

### 4️⃣ Testar!

```powershell
# Teste local (sempre funciona):
Invoke-WebRequest http://127.0.0.1:8000/webhook/whatsapp -Method POST -Body "test=1"

# Teste via ngrok (substitua pela sua URL):
Invoke-WebRequest https://SUA-URL.ngrok-free.app/webhook/whatsapp -Method POST -Body "test=1"
```

---

## 🔧 SCRIPTS AUTOMATIZADOS

### Opção A: Usar START_NGROK.bat
```batch
START_NGROK.bat
```
Este script mantém o servidor rodando mesmo se ele parar.

### Opção B: PowerShell em nova janela (já rodando!)
O servidor já está rodando em uma janela separada.

---

## 📊 VERIFICAR STATUS

```powershell
# Ver se servidor está rodando:
netstat -ano | Select-String "8000"

# Deve mostrar algo como:
# TCP    0.0.0.0:8000    0.0.0.0:0    LISTENING    1234

# Testar servidor:
Invoke-WebRequest http://127.0.0.1:8000 -UseBasicParsing

# Deve retornar: StatusCode 200
```

---

## 🌐 VERIFICAR NGROK

### Dashboard Local
Abra: http://127.0.0.1:4040

Aqui você vê:
- ✅ Status do túnel
- ✅ URL pública atual
- ✅ Todas as requisições recebidas
- ✅ Logs detalhados

### API do Ngrok
```powershell
$ngrok = Invoke-RestMethod http://127.0.0.1:4040/api/tunnels
$ngrok.tunnels[0].public_url
```

Isso mostra sua URL atual do ngrok.

---

## 📱 FLUXO COMPLETO DE TESTE

### Teste 1: Servidor Local
```powershell
Invoke-WebRequest http://127.0.0.1:8000
# Esperado: StatusCode 200
```
✅ Se funcionar: Servidor OK!

### Teste 2: Webhook Local
```powershell
Invoke-WebRequest http://127.0.0.1:8000/webhook/whatsapp -Method POST -Body "From=whatsapp:+5521999999999&Body=Teste"
# Esperado: StatusCode 200, Content: "OK"
```
✅ Se funcionar: Webhook OK!

### Teste 3: Via Ngrok
```powershell
# Substitua pela sua URL:
Invoke-WebRequest https://SUA-URL.ngrok-free.app
# Esperado: StatusCode 200
```
✅ Se funcionar: Ngrok OK!

### Teste 4: Webhook via Ngrok
```powershell
Invoke-WebRequest https://SUA-URL.ngrok-free.app/webhook/whatsapp -Method POST -Body "test=1"
# Esperado: StatusCode 200
```
✅ Se funcionar: TUDO PRONTO! 🎉

### Teste 5: WhatsApp Real
1. Configure webhook no Twilio (passo 3️⃣ acima)
2. Envie mensagem do seu WhatsApp
3. Veja nos logs:
   ```powershell
   Get-Content storage\logs\lumen-2025-12-23.log -Wait
   ```

---

## ❓ TROUBLESHOOTING

### ❌ "Nenhuma conexão pôde ser feita"
**Causa:** Servidor não está rodando

**Solução:**
```powershell
# Ver processos PHP:
Get-Process php

# Se não houver nenhum, inicie:
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd c:\Projetos\saas; php -S 0.0.0.0:8000 -t public"
```

### ❌ "ERR_NGROK_3200 - endpoint is offline"
**Causa:** Túnel ngrok expirou ou foi fechado

**Solução:**
```bash
# Reinicie ngrok:
ngrok http 8000

# Copie nova URL
# Atualize no Twilio
```

### ❌ "ERR_NGROK_8012"
**Causa:** Ngrok não consegue conectar ao servidor

**Solução:** Certifique-se de que o servidor está em `0.0.0.0:8000` (não `127.0.0.1:8000`)

```powershell
# Verificar porta:
netstat -ano | Select-String "8000"

# Deve mostrar: 0.0.0.0:8000
```

### ❌ Webhook não processa mensagem
**Causa:** Tenant não configurado ou erro no código

**Solução:**
```powershell
# Ver logs:
Get-Content storage\logs\lumen-2025-12-23.log -Tail 50
```

---

## 🎯 CHECKLIST FINAL

Antes de enviar mensagem real:

- [ ] Servidor PHP rodando em `0.0.0.0:8000`
- [ ] Teste local funciona: `http://127.0.0.1:8000`
- [ ] Ngrok rodando
- [ ] Dashboard ngrok acessível: `http://127.0.0.1:4040`
- [ ] URL do ngrok anotada
- [ ] Teste via ngrok funciona
- [ ] Webhook configurado no Twilio com URL do ngrok
- [ ] WhatsApp conectado ao sandbox Twilio
- [ ] Logs sendo monitorados

Se todos ✅, pode enviar mensagem do WhatsApp! 🚀

---

**Última atualização:** 23/12/2025 17:05
