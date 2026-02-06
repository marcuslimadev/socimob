# Script de Processamento em Lote de Leads

## 📋 Descrição

Script automatizado para processar leads em lote, realizando duas operações principais:

1. **Criar Perfis Completos**: Cria conversas para todos os leads que ainda não têm uma conversa associada
2. **Enviar SMS Pendentes**: Envia SMS com link de WhatsApp para todos os leads que ainda não receberam

## 🚀 Como Usar

### Execução Local

#### Teste (Dry-Run)
```bash
# Windows PowerShell
.\process-leads.ps1 -DryRun

# Linux/Mac ou CMD
php process_all_leads.php --dry-run
```

#### Execução Real
```bash
# Windows PowerShell
.\process-leads.ps1

# Linux/Mac ou CMD
php process_all_leads.php
```

### Execução em Produção

#### Teste em Produção (Dry-Run)
```powershell
.\process-leads.ps1 -Production -DryRun
```

#### Execução Real em Produção
```powershell
.\process-leads.ps1 -Production
```

## ⚙️ Opções Disponíveis

### Flags do Script PHP

- `--dry-run`: Simula a execução sem fazer alterações no banco
- `--only-sms`: Apenas envia SMS, não cria perfis
- `--only-profiles`: Apenas cria perfis, não envia SMS

### Parâmetros do PowerShell

- `-DryRun`: Modo simulação
- `-Production`: Executa no servidor remoto
- `-OnlySms`: Apenas envia SMS
- `-OnlyProfiles`: Apenas cria perfis

## 📊 Exemplos de Uso

### 1. Criar perfis e enviar SMS localmente (teste)
```powershell
.\process-leads.ps1 -DryRun
```

### 2. Apenas criar perfis em produção
```powershell
.\process-leads.ps1 -Production -OnlyProfiles
```

### 3. Apenas enviar SMS em produção (teste primeiro)
```powershell
# Teste
.\process-leads.ps1 -Production -OnlySms -DryRun

# Se estiver OK, executar de verdade
.\process-leads.ps1 -Production -OnlySms
```

### 4. Processar tudo em produção
```powershell
# SEMPRE testar primeiro
.\process-leads.ps1 -Production -DryRun

# Depois executar
.\process-leads.ps1 -Production
```

## 🔍 O Que o Script Faz

### ETAPA 1: Criar Perfis

1. Busca todos os leads sem conversa associada
2. Para cada lead:
   - Cria uma nova conversa
   - Vincula ao lead
   - Define status como 'ativa'
   - Define stage como 'qualificacao'
   - Define canal como 'manual'

### ETAPA 2: Enviar SMS

1. Busca todos os leads com `sms_enviado = false` ou `NULL`
2. Para cada lead (máximo 100 por execução):
   - Cria um short link único
   - Monta mensagem personalizada com nome da empresa
   - Envia SMS via Twilio
   - Marca `sms_enviado = true`
   - Registra `sms_enviado_em` com timestamp
   - Aguarda 0.5 segundo antes do próximo (rate limiting)

### Formato da Mensagem SMS
```
Olá! Sou a assistente virtual da {EMPRESA}. 
Vi que você tem interesse em imóveis. 
Vamos conversar pelo WhatsApp? 
Clique aqui: https://lojadaesquina.store/api/w/{CODE}
```

## 📈 Saída do Script

```
═══════════════════════════════════════════════════════════
   PROCESSAMENTO EM LOTE DE LEADS - SOCIMOB
═══════════════════════════════════════════════════════════

🔍 MODO DRY-RUN ATIVADO - Nenhuma alteração será feita

📋 ETAPA 1: Criando perfis completos para leads
───────────────────────────────────────────────────────────
Leads sem conversa: 3

  → Lead #111: Luciane Santos (5531986685427)
    ✓ Conversa criada (ID: 456)
  → Lead #108: Alonso Silva (5531991561129)
    ✓ Conversa criada (ID: 457)

✅ Perfis criados: 3

📱 ETAPA 2: Enviando SMS para leads pendentes
───────────────────────────────────────────────────────────
Leads pendentes de SMS: 89

  → Lead #141: Angela (31994393553)
    ✓ SMS enviado (Code: ABC123)
  → Lead #133: Ana Paula (31987816795)
    ✓ SMS enviado (Code: XYZ789)

✅ SMS enviados: 89

═══════════════════════════════════════════════════════════
   PROCESSAMENTO CONCLUÍDO
═══════════════════════════════════════════════════════════

Perfis criados: 3
SMS enviados: 89
Tempo total: 45.23s
```

## 🔒 Segurança e Limitações

### Limite de SMS
- Máximo de **100 SMS por execução**
- Proteção contra exceder quota da Twilio
- Se houver mais leads, execute novamente

### Rate Limiting
- Delay de 0.5 segundo entre cada SMS
- Evita sobrecarga na API Twilio
- Tempo total estimado: ~50 segundos para 100 SMS

### Isolamento Multi-Tenant
- Respeita `tenant_id` dos leads
- Short links vinculados ao tenant correto
- Mensagens personalizadas por tenant

## 🐛 Tratamento de Erros

### Erros de SMS
- Registrados no log do Laravel
- Contador de erros exibido no final
- Lead não é marcado como enviado se falhar
- Pode reprocessar leads com falha

### Erros de Criação de Perfil
- Exception capturada e logada
- Continua processando próximos leads
- Não interrompe execução completa

## 📝 Logs

Todos os erros são registrados em:
- `storage/logs/laravel.log` (local)
- `~/domains/lojadaesquina.store/public_html/storage/logs/laravel.log` (produção)

### Exemplo de Log de Erro
```
[2026-02-06 20:00:00] local.ERROR: Erro ao enviar SMS para lead 123
{
  "error": "Invalid phone number",
  "lead_id": 123,
  "telefone": "+55319999999"
}
```

## 🔄 Reprocessamento

### Se algo falhar:
1. Corrija o problema (telefone inválido, configuração Twilio, etc)
2. Execute novamente o script
3. Apenas leads pendentes serão processados

### Leads já processados:
- Leads com conversa não terão nova conversa criada
- Leads com `sms_enviado = true` não receberão SMS novamente

## ⚠️ Importante

1. **SEMPRE use --dry-run primeiro** para verificar o que será feito
2. **Verifique a quota Twilio** antes de enviar muitos SMS
3. **Execute em horário comercial** para melhor taxa de resposta
4. **Monitore os logs** durante a execução em produção
5. **Limite de 100 SMS** por segurança - execute múltiplas vezes se necessário

## 🆘 Troubleshooting

### "No matches found" ou "Leads sem conversa: 0"
- Normal se todos os leads já têm conversas
- Verifique se há leads no banco: `SELECT COUNT(*) FROM leads;`

### "Leads pendentes de SMS: 0"
- Todos os leads já receberam SMS
- Para reenviar, atualize: `UPDATE leads SET sms_enviado = false;`

### Erros de conexão Twilio
- Verifique credenciais no `.env`
- Confirme saldo na conta Twilio
- Teste com um lead específico primeiro

### Telefones inválidos
- Script valida apenas se campo não está vazio
- Telefones inválidos causarão erro no envio
- Corrija manualmente: `UPDATE leads SET telefone = '+5531...' WHERE id = X;`

## 📞 Suporte

Em caso de dúvidas ou problemas:
1. Verifique os logs
2. Execute em modo dry-run
3. Teste com poucos leads primeiro (edite `$maxPerRun`)
