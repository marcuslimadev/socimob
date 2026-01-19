# 🔧 Script de Correção de Leads - Guia de Uso

## 📋 O que o script faz

1. **Remove duplicações** - Leads com mesmo telefone
2. **Cria leads faltantes** - Para conversas sem lead_id
3. **Cria clientes** - Para leads sem user_id

---

## 🌐 Via cURL (HTTP)

### Local (desenvolvimento)
```bash
curl -X POST http://127.0.0.1:8000/fix_leads_duplicados.php \
  -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=" \
  -H "Content-Type: application/json"
```

### Produção
```bash
curl -X POST https://lojadaesquina.store/fix_leads_duplicados.php \
  -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8=" \
  -H "Content-Type: application/json"
```

### Windows PowerShell
```powershell
Invoke-WebRequest -Uri "http://127.0.0.1:8000/fix_leads_duplicados.php" `
  -Method POST `
  -Headers @{"X-Admin-Secret"="ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8="} `
  -ContentType "application/json" | Select-Object -ExpandProperty Content | ConvertFrom-Json | ConvertTo-Json -Depth 10
```

---

## 💻 Via CLI (linha de comando)

```bash
php fix_leads_duplicados.php
```

---

## 📊 Resposta JSON (HTTP)

```json
{
  "success": true,
  "message": "Script executado com sucesso",
  "estatisticas": {
    "total_leads": 150,
    "total_conversas": 200,
    "conversas_com_lead": 195,
    "leads_com_cliente": 145
  },
  "acoes": {
    "leads_duplicados_removidos": 5,
    "leads_mesclados": 3,
    "leads_criados": 5,
    "clientes_criados": 5
  },
  "log": "... output completo do script ..."
}
```

---

## 🔐 Segurança

- **Autenticação via header** `X-Admin-Secret`
- **Secret key** configurada em `.env` (`DEPLOY_SECRET`)
- **CLI não requer autenticação** (apenas HTTP)

---

## ⚠️ Cuidados

- ✅ Faz backup automático via merge (não perde dados)
- ✅ Mantém sempre o lead mais antigo
- ✅ Reatribui conversas e matches automaticamente
- ⚠️ Execute em horário de baixo tráfego
- ⚠️ Verifique os logs antes de aplicar em produção

---

## 🎯 Casos de Uso

### 1. Limpar base após testes
```bash
curl -X POST http://127.0.0.1:8000/fix_leads_duplicados.php \
  -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8="
```

### 2. Corrigir webhook que não criou leads
```bash
# Após corrigir o código, execute para criar leads faltantes
curl -X POST https://lojadaesquina.store/fix_leads_duplicados.php \
  -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8="
```

### 3. Sincronizar clientes
```bash
# Garante que todos os leads tenham clientes
curl -X POST http://127.0.0.1:8000/fix_leads_duplicados.php \
  -H "X-Admin-Secret: ULqVBREGLgTL2cDw/WauzXgGuNxGLIG4/HcG3CdXwf8="
```

---

## 📝 Exemplos de Output

### Sucesso
```json
{
  "success": true,
  "message": "Script executado com sucesso",
  "acoes": {
    "leads_duplicados_removidos": 3,
    "leads_criados": 2,
    "clientes_criados": 5
  }
}
```

### Erro de autenticação
```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Header X-Admin-Secret inválido"
}
```
