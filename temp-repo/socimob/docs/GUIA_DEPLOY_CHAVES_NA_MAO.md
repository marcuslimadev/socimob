# 🚀 Guia Rápido - Deploy Integração Chaves na Mão

## Pré-requisitos

- ✅ Código commitado e pushed para GitHub
- ✅ Credenciais configuradas no `.env.production`
- ✅ Servidor com acesso ao banco de dados

## Passo a Passo

### 1. Atualizar Código no Servidor

Via PuTTY/SSH:

```bash
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
```

### 2. Executar Migration

```bash
/opt/alt/php83/usr/bin/php artisan migrate --force
```

**Esperado:**
```
Running migration: 2025_12_26_010500_add_chaves_na_mao_integration_to_leads
Migrated: 2025_12_26_010500_add_chaves_na_mao_integration_to_leads
```

### 3. Adicionar Credenciais

Editar `.env` no servidor (via cPanel File Manager ou vim):

```env
EXCLUSIVA_MAIL_CHAVES_NA_MAO=contato@exclusivalarimoveis.com.br
EXCLUSIVA_CHAVES_NA_MAO=d825c542e26df27c9fe696c391ee590
```

### 4. Limpar Cache

```bash
curl "https://lojadaesquina.store/opcache_clear.php"
```

**Esperado:**
```
OPcache limpo com sucesso!
Enabled: yes
Scripts em cache: X
```

### 5. Verificar Status

```bash
/opt/alt/php83/usr/bin/php artisan chaves:sync status
```

**Esperado:**
```
📊 Status da integração Chaves na Mão

+--------------------+------------+
| Status             | Quantidade |
+--------------------+------------+
| Aguardando envio   | 0          |
| Enviados com sucesso | 0        |
| Com erro           | 0          |
| Não processados    | X          |
+--------------------+------------+
```

### 6. Testar Integração

```bash
/opt/alt/php83/usr/bin/php artisan chaves:sync test
```

**Esperado (sucesso):**
```
🧪 Testando integração Chaves na Mão...
📋 Testando com lead: João Silva (ID: 123)
✅ Lead enviado com sucesso!
   Status Code: 201
```

**Esperado (erro):**
```
❌ Falha no envio:
   Erro: Erro de autenticação - verificar credenciais
   Status Code: 401
```

## Validação

### Via HTTP

```bash
# Get status
curl -X GET "https://lojadaesquina.store/api/admin/chaves-na-mao/status" \
     -H "Authorization: Bearer SEU_TOKEN"

# Test integration
curl -X POST "https://lojadaesquina.store/api/admin/chaves-na-mao/test" \
     -H "Authorization: Bearer SEU_TOKEN" \
     -H "Content-Type: application/json"
```

### Via Logs

```bash
tail -f storage/logs/lumen-$(date +%Y-%m-%d).log | grep -i "chaves"
```

Ou via HTTP:

```bash
curl "https://lojadaesquina.store/read_logs.php?secret=ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=&date=$(date +%Y-%m-%d)&filter=chaves"
```

## Monitoramento Contínuo

### Verificar Leads Falhados

```bash
/opt/alt/php83/usr/bin/php artisan chaves:sync status
```

### Retry Automático

Configurar cron job (opcional):

```cron
*/30 * * * * /opt/alt/php83/usr/bin/php ~/domains/lojadaesquina.store/public_html/artisan chaves:sync retry >> /dev/null 2>&1
```

Isso tenta reenviar leads falhados a cada 30 minutos.

## Troubleshooting

### ❌ Erro: "Credenciais não configuradas"

**Causa:** Variáveis `.env` não carregadas

**Solução:**
1. Verificar se `.env` existe no servidor
2. Confirmar que contém `EXCLUSIVA_MAIL_CHAVES_NA_MAO` e `EXCLUSIVA_CHAVES_NA_MAO`
3. Limpar OPcache: `curl "https://lojadaesquina.store/opcache_clear.php"`

### ❌ Erro 401 - Autenticação

**Causa:** Credenciais incorretas ou formato errado

**Solução:**
1. Verificar email e token no `.env`
2. Testar autenticação manualmente:
   ```bash
   echo -n "email:token" | base64
   curl -H "Authorization: Basic <base64>" https://api.chavesnamao.com.br/leads
   ```

### ❌ Leads não sendo enviados automaticamente

**Causa:** Observer não registrado

**Solução:**
1. Verificar `bootstrap/app.php` contém:
   ```php
   App\Models\Lead::observe(App\Observers\LeadObserver::class);
   ```
2. Limpar cache
3. Criar lead de teste via interface

### ❌ Migration falha

**Causa:** Campos já existem ou erro de sintaxe

**Solução:**
```bash
# Reverter migration
/opt/alt/php83/usr/bin/php artisan migrate:rollback --step=1

# Tentar novamente
/opt/alt/php83/usr/bin/php artisan migrate --force
```

## Checklist de Validação

- [ ] Código atualizado no servidor (`git pull`)
- [ ] Migration executada com sucesso
- [ ] Credenciais adicionadas ao `.env`
- [ ] OPcache limpo
- [ ] `chaves:sync status` executado
- [ ] `chaves:sync test` executado com sucesso
- [ ] Logs não mostram erros críticos
- [ ] Criar lead de teste via interface e verificar envio automático

## Próximos Passos

Após validação bem-sucedida:

1. **Monitorar logs** por 24h para detectar erros
2. **Verificar dashboard** do Chaves na Mão para confirmar recebimento
3. **Configurar cron** para retry automático (opcional)
4. **Documentar** credenciais de produção em local seguro

## Contatos

- **Suporte Técnico:** Marcus Lima
- **Documentação:** `docs/INTEGRACAO_CHAVES_NA_MAO.md`
- **Logs:** `https://lojadaesquina.store/read_logs.php?secret=...`
