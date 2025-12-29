# 🤖 Guia de Teste: Personalização do Prompt da IA

## O que foi implementado

✅ **Interface Admin**: Campo de 2000 caracteres em Configurações > Integrações
✅ **Backend**: Métodos para salvar/buscar prompt customizado (AppSetting)
✅ **Rotas API**: GET/POST/DELETE `/api/admin/settings/ai-prompt`
✅ **OpenAIService**: Modificado para usar prompt customizado quando disponível

## Como Testar

### 1. Acessar Configurações
```
http://127.0.0.1:8000/app/configuracoes.html
```
- Login como admin
- Ir para aba "Integrações"
- Rolar até seção "Prompt da IA"

### 2. Configurar Prompt Customizado

**Exemplo de prompt customizado:**
```
Você é um assistente imobiliário ESPECIALIZADO EM IMÓVEIS DE LUXO.

REGRAS OBRIGATÓRIAS:
1. SEMPRE mencione amenities de alto padrão (spa, adega, salão de festas)
2. Use linguagem sofisticada mas acessível
3. Destaque diferenciais exclusivos de cada imóvel
4. Seja discreto ao perguntar sobre capacidade financeira

ABORDAGEM:
- Primeiro, entenda o estilo de vida do cliente
- Mostre apenas imóveis que combinem com o perfil
- NUNCA use emojis em excesso
- Tom formal mas caloroso

{$propertiesContext}

Cliente: {$message}
```

**Variáveis disponíveis:**
- `{$assistantName}` - Nome do assistente (configurável)
- `{$audioInstruction}` - Instruções para áudio
- `{$propertiesContext}` - Lista de imóveis disponíveis

### 3. Salvar Prompt
- Cole o prompt no campo
- Clique em "Salvar Prompt da IA"
- Verifique mensagem de sucesso

### 4. Testar via WhatsApp

**Opção A - Produção (se configurado):**
```
Envie mensagem para: +55 11 4040-5050
Texto: "Oi, quero um apartamento com 3 quartos"
```

**Opção B - Local (teste direto):**
```bash
cd c:\xampp\htdocs\simplessaas
php test_ai_custom_prompt.php
```

### 5. Verificar Logs

**Logs locais:**
```
backend/storage/logs/lumen-2024-12-25.log
```

**Buscar por:**
```
[OpenAI] Usando prompt CUSTOMIZADO do administrador
```

**Deve conter:**
```
[2024-12-25 15:30:45] local.INFO: [OpenAI] Usando prompt CUSTOMIZADO do administrador  
{"length":450,"preview":"Você é um assistente imobiliário ESPECIALIZADO EM IMÓVEIS DE LUXO..."}
```

### 6. Comparar Respostas

**COM prompt padrão:**
- Mais emojis (🎯, 📋, 1️⃣, 2️⃣)
- Tom casual e empático
- Foco em guiar o cliente passo a passo

**COM prompt customizado (exemplo acima):**
- Linguagem mais sofisticada
- Ênfase em luxo e exclusividade
- Tom formal mas caloroso
- Menos emojis

## Comportamento Esperado

### Prioridade
1. **Prompt customizado existe?** → Usa 100% o customizado
2. **Prompt customizado vazio?** → Usa prompt padrão do sistema

### Variáveis
- `{$propertiesContext}` é **SEMPRE** injetado (lista de imóveis)
- `{$assistantName}` vem de `AppSetting::getValue('ai_name', 'Assistente Virtual')`
- `{$audioInstruction}` detecta se mensagem é áudio

### Logs
Cada resposta da IA gera log mostrando qual prompt foi usado:
```
[OpenAI] Usando prompt CUSTOMIZADO do administrador  // Se configurado
[OpenAI] Usando prompt PADRÃO do sistema              // Se não configurado
```

## Casos de Teste

### Teste 1: Primeiro Acesso
- ✅ Campo vazio (nenhum prompt customizado)
- ✅ Sistema usa prompt padrão

### Teste 2: Salvar Prompt
- ✅ Salva texto com 2000 caracteres
- ✅ Contador mostra "0 / 2000"
- ✅ Mensagem: "Prompt salvo com sucesso!"

### Teste 3: Carregar Prompt
- ✅ Recarregar página
- ✅ Prompt aparece no campo
- ✅ Contador atualiza

### Teste 4: Atendimento IA
- ✅ WhatsApp usa prompt customizado
- ✅ Logs mostram "CUSTOMIZADO"
- ✅ Resposta segue instruções do admin

### Teste 5: Excluir Prompt
- ✅ Clicar "Excluir Prompt"
- ✅ Campo limpa
- ✅ Próximo atendimento usa prompt padrão
- ✅ Logs mostram "PADRÃO"

## Troubleshooting

### Prompt não está sendo usado
1. **Verificar salvamento:**
   ```sql
   SELECT * FROM app_settings 
   WHERE setting_key = 'ai_prompt_custom' 
   AND tenant_id = 1;
   ```

2. **Verificar logs:**
   ```
   tail -f backend/storage/logs/lumen-2024-12-25.log | grep "OpenAI"
   ```

3. **Cache:**
   ```bash
   # Limpar cache do AppSetting (se houver)
   php artisan cache:clear
   ```

### Frontend não carrega prompt
1. **DevTools Console:**
   - F12 → Console
   - Deve mostrar: `"Prompt da IA carregado: 450 caracteres"`

2. **Network Tab:**
   - Verificar `/api/admin/settings/ai-prompt`
   - Status 200 com JSON

### Backend não salva
1. **Verificar autenticação:**
   - Token válido?
   - Tenant correto?

2. **Verificar tabela:**
   ```sql
   DESCRIBE app_settings;
   -- Deve ter: id, tenant_id, setting_key, setting_value, created_at, updated_at
   ```

## Comandos Úteis

### Verificar prompt atual
```bash
mysql -u root -e "USE exclusiva; SELECT setting_value FROM app_settings WHERE setting_key='ai_prompt_custom' AND tenant_id=1;"
```

### Limpar prompt
```bash
mysql -u root -e "USE exclusiva; DELETE FROM app_settings WHERE setting_key='ai_prompt_custom' AND tenant_id=1;"
```

### Ver logs em tempo real
```bash
Get-Content backend\storage\logs\lumen-$(Get-Date -Format 'yyyy-MM-dd').log -Wait -Tail 20
```

## Validação Final

- [ ] Campo aparece em Configurações
- [ ] Salvar funciona (mensagem de sucesso)
- [ ] Carregar funciona (prompt aparece ao recarregar)
- [ ] Contador de caracteres funciona
- [ ] Logs mostram "CUSTOMIZADO" ou "PADRÃO"
- [ ] WhatsApp responde com prompt customizado
- [ ] Excluir funciona (volta ao padrão)
- [ ] Variáveis {$propertiesContext} são injetadas

---

**Status**: ✅ Implementação completa
**Arquivo modificado**: `app/Services/OpenAIService.php` (linhas 303-370)
**Prioridade**: Prompt do admin SEMPRE prevalece sobre padrão
