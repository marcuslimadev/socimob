# 🔑 Configurar OpenAI API para Respostas Automáticas

## ❌ Problema Atual

O webhook recebe as mensagens corretamente, mas **não responde** porque a OpenAI API Key não está configurada.

**Erro nos logs:**
```
[2025-12-25 20:59:00] production.ERROR: OpenAI Transcription Error {"http_code":401}
[2025-12-25 20:59:00] production.ERROR: ❌ IA falhou ao processar mensagem {"error":"Chat completion failed"}
```

## ✅ Solução

### 1. Obter API Key da OpenAI

1. Acesse: https://platform.openai.com/api-keys
2. Faça login ou crie uma conta
3. Clique em "Create new secret key"
4. Copie a chave (começa com `sk-...`)

### 2. Configurar em Produção

#### Opção A: Via cPanel File Manager

1. Acesse cPanel da Hostinger
2. File Manager → `public_html/.env`
3. Adicione as linhas na seção de configurações do tenant:
   ```env
   # ===========================
   # TENANT: EXCLUSIVA
   # ===========================
   
   # Twilio WhatsApp
   TWILIO_ACCOUNT_SID=ACxxxxxxxxxx
   TWILIO_AUTH_TOKEN=xxxxxxxxxx
   TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
   
   # OpenAI (Respostas automáticas)
   OPENAI_API_KEY=sk-sua-chave-aqui
   OPENAI_MODEL=gpt-4o-mini
   ```

#### Opção B: Via SSH

```bash
ssh usuario@lojadaesquina.store
cd domains/exclusivalarimoveis.com/public_html
nano .env

# Adicione:
OPENAI_API_KEY=sk-sua-chave-aqui
OPENAI_MODEL=gpt-4o-mini

# Salve: Ctrl+O, Enter, Ctrl+X
```

### 3. Configurar no Banco de Dados (Alternativa)

A API key também pode ser configurada por tenant no banco:

```sql
-- Conectar ao banco exclusiva
UPDATE tenant_configs 
SET value = 'sk-sua-chave-aqui' 
WHERE tenant_id = 1 
  AND key = 'api_key_openai';

-- Se não existir, criar:
INSERT INTO tenant_configs (tenant_id, `key`, value) 
VALUES (1, 'api_key_openai', 'sk-sua-chave-aqui');
```

### 4. Testar

Depois de configurar, envie uma mensagem de teste:

```powershell
$payload = @{
    MessageSid = "SM" + (New-Guid).ToString("N").Substring(0,32)
    From = "whatsapp:+5511999999999"
    To = "whatsapp:+551140405050"
    Body = "Olá, quero alugar um apartamento"
    ProfileName = "Cliente Teste"
}
curl "https://lojadaesquina.store/webhook/whatsapp" -Method POST -Body $payload
```

## 📊 Como Funciona

1. **Mensagem recebida** → Webhook processa
2. **OpenAI analisa** → Entende a intenção
3. **Gera resposta** → Baseada no contexto e histórico
4. **Envia via Twilio** → Responde automaticamente no WhatsApp

## 💰 Custos OpenAI

- **gpt-4o-mini** (recomendado): ~$0.15 por 1M tokens entrada, ~$0.60 por 1M tokens saída
- **gpt-4o**: Mais caro, mais inteligente
- **gpt-3.5-turbo**: Mais barato, menos preciso

Uma conversa média (10 mensagens) custa menos de $0.01 com gpt-4o-mini.

## 🔒 Segurança

⚠️ **NUNCA commite a API key no git!**
- Arquivo `.env` está no `.gitignore`
- Use variáveis de ambiente em produção
- Rotacione a chave periodicamente

## 🎯 Próximos Passos

Após configurar a OpenAI:
1. ✅ Sistema responderá automaticamente
2. ✅ Extrairá dados do lead (nome, email, telefone)
3. ✅ Detectará intenção (aluguel, compra, visita)
4. ✅ Qualificará leads automaticamente
5. ✅ Criará tarefas para corretores

---

**Status Atual:**
- ✅ Webhook funcionando
- ✅ Recepção de mensagens OK
- ✅ Criação de leads OK
- ❌ **Respostas automáticas - Aguardando OpenAI API Key**
