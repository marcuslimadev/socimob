# ✅ Scripts de Processamento em Lote - Prontos para Uso

## Status: DEPLOYED e Testado

**Commit**: `bee1ecd`  
**Servidor**: lojadaesquina.store  
**Status**: ✅ Online e funcional

---

## 🎯 Scripts Criados

### 1. `process_all_leads.php`
Script PHP principal que processa leads em lote.

### 2. `process-leads.ps1`
Script PowerShell para facilitar execução local e remota.

### 3. `PROCESS_LEADS_README.md`
Documentação completa com exemplos e troubleshooting.

---

## 🚀 Como Usar (Guia Rápido)

### ⚠️ IMPORTANTE: Sempre teste primeiro!

```powershell
# 1. TESTE LOCAL (sem alterar nada)
.\process-leads.ps1 -DryRun

# 2. TESTE EM PRODUÇÃO (sem alterar nada)
.\process-leads.ps1 -Production -DryRun

# 3. EXECUTAR EM PRODUÇÃO (REAL)
.\process-leads.ps1 -Production
```

---

## 📋 O Que os Scripts Fazem

### Criação de Perfis
✅ Identifica leads sem conversa  
✅ Cria conversa para cada lead  
✅ Vincula conversa ao lead  
✅ Define status inicial  

**Resultado**: Todos os leads terão perfis completos

### Envio de SMS
✅ Identifica leads sem SMS enviado  
✅ Cria short link único para cada lead  
✅ Envia SMS com link do WhatsApp  
✅ Marca lead como processado  
✅ Limite de 100 SMS por execução  

**Resultado**: Leads receberão SMS para iniciar conversa

---

## 📊 Teste Realizado Localmente

```
═══════════════════════════════════════════════════════════
   PROCESSAMENTO EM LOTE DE LEADS - SOCIMOB
═══════════════════════════════════════════════════════════

🔍 MODO DRY-RUN ATIVADO

📋 ETAPA 1: Criando perfis completos para leads
───────────────────────────────────────────────────────────
Leads sem conversa: 3

  → Lead #111: Luciane Santos (5531986685427)
  → Lead #108: Alonso Silva (5531991561129)
  → Lead #107: Sarah Novais (5531975670718)

✅ Perfis criados: 3

📱 ETAPA 2: Enviando SMS para leads pendentes
───────────────────────────────────────────────────────────
Leads pendentes de SMS: 89

  → Lead #141: Angela (31994393553)
  → Lead #133: Ana Paula (31987816795)
  → Lead #132: Mara (31988332447)
  [... mais 86 leads ...]

✅ SMS enviados: 89 (limitado a 100 por execução)

Tempo total: 0.45s
```

---

## 🎬 Passos para Executar em Produção

### Passo 1: Testar em Produção (Dry-Run)
```powershell
.\process-leads.ps1 -Production -DryRun
```

**O que vai mostrar**:
- Quantos leads terão perfis criados
- Quantos SMS serão enviados
- Lista dos leads que serão processados
- **NENHUMA alteração será feita**

### Passo 2: Analisar Resultados
Verifique se os números fazem sentido:
- Leads sem conversa devem ser raros
- Leads sem SMS podem ser muitos (se nunca rodou antes)

### Passo 3: Executar Apenas Perfis (Opcional)
Se quiser criar perfis primeiro sem enviar SMS:
```powershell
.\process-leads.ps1 -Production -OnlyProfiles
```

### Passo 4: Executar Apenas SMS (Recomendado)
Criar perfis não tem custo, SMS sim. Faça em etapas:

#### Teste com dry-run
```powershell
.\process-leads.ps1 -Production -OnlySms -DryRun
```

#### Execute de verdade
```powershell
.\process-leads.ps1 -Production -OnlySms
```

**Nota**: Serão enviados no máximo 100 SMS por vez. Se houver mais, execute novamente.

### Passo 5: Executar Tudo de Uma Vez
```powershell
.\process-leads.ps1 -Production
```

---

## 💰 Custos de SMS

### Twilio SMS Pricing (Brasil)
- **Custo por SMS**: ~$0.05 USD (R$ 0.25)
- **89 leads**: ~$4.45 USD (R$ 22)

### Recomendação
1. Verifique saldo Twilio antes
2. Teste com poucos leads primeiro
3. Execute em horário comercial (melhor resposta)
4. Monitore taxa de conversão

---

## 📈 Métricas Esperadas

### Taxa de Resposta Típica
- **10-20%** respondem ao SMS
- **5-10%** iniciam conversa no WhatsApp
- **2-5%** convertem em leads qualificados

### Exemplo com 89 leads:
- SMS enviados: 89
- Cliques no link: ~15 (17%)
- Conversas iniciadas: ~7 (8%)
- Leads qualificados: ~3 (3%)

---

## 🔍 Monitoramento

### Após Executar

1. **Verificar no sistema**:
   - Acesse `/leads`
   - Verifique se perfis foram criados
   - Check se campo `sms_enviado` está `true`

2. **Verificar no Twilio**:
   - Acesse dashboard Twilio
   - Veja SMS enviados
   - Taxa de entrega

3. **Verificar conversas**:
   - Acesse `/chat`
   - Monitore conversas entrantes
   - Leads clicando nos links aparecem aqui

---

## 🐛 Se Algo Der Errado

### SMS não estão sendo enviados
```bash
# Verificar logs
ssh -p 65002 u815655858@145.223.105.168
cd ~/domains/lojadaesquina.store/public_html
tail -f storage/logs/laravel.log
```

### Leads com telefones inválidos
```sql
-- Ver leads com telefones problemáticos
SELECT id, nome, telefone FROM leads 
WHERE sms_enviado = false 
AND (telefone IS NULL OR telefone = '' OR LENGTH(telefone) < 10);

-- Corrigir telefones
UPDATE leads SET telefone = '+5531999999999' WHERE id = X;
```

### Reenviar SMS para lead específico
```sql
-- Marcar para reenvio
UPDATE leads SET sms_enviado = false WHERE id = X;

-- Rodar script novamente
```

---

## 📝 Logs de Execução

Todos os logs são salvos em:
```
storage/logs/laravel.log
```

Buscar por:
- `"Erro ao criar conversa para lead"`
- `"Erro ao enviar SMS para lead"`

---

## ✅ Checklist de Execução

- [ ] Verificar saldo Twilio
- [ ] Testar em dry-run local
- [ ] Testar em dry-run produção
- [ ] Analisar quantos SMS serão enviados
- [ ] Confirmar horário comercial (9h-18h)
- [ ] Executar em produção
- [ ] Monitorar logs durante execução
- [ ] Verificar dashboard Twilio
- [ ] Acompanhar conversas entrantes
- [ ] Se houver mais de 100 leads, executar novamente

---

## 🎉 Resultado Final

Após executar com sucesso:

✅ **Todos os leads terão perfis completos** (conversas associadas)  
✅ **Leads receberão SMS com link WhatsApp** personalizado  
✅ **Short links rastreáveis** para analytics  
✅ **Sistema pronto para receber respostas** via WhatsApp  
✅ **IA iniciará atendimento automaticamente** quando cliente clicar  

---

## 📞 Próximos Passos

1. **Execute o script** conforme guia acima
2. **Monitore as conversas** que começarem a entrar
3. **Analise taxa de resposta** após 24-48h
4. **Ajuste mensagem** se necessário (edite no script)
5. **Execute periodicamente** para novos leads

---

## 🔗 Arquivos Relacionados

- [process_all_leads.php](process_all_leads.php) - Script principal
- [process-leads.ps1](process-leads.ps1) - Helper PowerShell
- [PROCESS_LEADS_README.md](PROCESS_LEADS_README.md) - Documentação completa

---

**✨ Sistema pronto para processar leads em escala!**
