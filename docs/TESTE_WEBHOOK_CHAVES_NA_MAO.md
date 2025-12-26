# 🧪 Guia de Testes - Webhook Chaves na Mão

## ✅ Testes Realizados com Sucesso

### Resultado dos Testes
- **Status**: ✅ Webhook funcionando corretamente
- **Data**: 26/12/2025
- **Ambiente**: Produção (lojadaesquina.store)

### Leads Criados nos Testes
| ID | Nome | Telefone | Quartos | Suítes | Garagem | Valor |
|----|------|----------|---------|--------|---------|-------|
| 4 | Maria Santos | 11988887777 | 4 | 3 | 2 | R$ 750.000 |

## 🔧 Como Testar Manualmente

### 1. PowerShell (Windows)

```powershell
# Payload de teste
$json = '{
  "id":"TEST001",
  "name":"João Silva",
  "phone":"31987654321",
  "email":"joao@example.com",
  "segment":"REAL_ESTATE",
  "ad":{
    "type":"Apartamento",
    "rooms":3,
    "suites":2,
    "garages":2,
    "price":500000,
    "neighborhood":"Savassi",
    "city":"Belo Horizonte"
  }
}'

# Enviar requisição
Invoke-RestMethod `
  -Uri "https://lojadaesquina.store/webhook/chaves-na-mao" `
  -Method POST `
  -Headers @{
    "Authorization" = "Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw"
    "Content-Type" = "application/json"
  } `
  -Body $json
```

**Resposta esperada:**
```json
{
  "success": true,
  "message": "Lead recebido e processado com sucesso",
  "lead_id": 4
}
```

### 2. cURL (Linux/Mac)

```bash
curl -X POST https://lojadaesquina.store/webhook/chaves-na-mao \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw" \
  -d '{
    "id":"TEST001",
    "name":"João Silva",
    "phone":"31987654321",
    "email":"joao@example.com",
    "segment":"REAL_ESTATE",
    "ad":{
      "type":"Apartamento",
      "rooms":3,
      "suites":2,
      "garages":2,
      "price":500000,
      "neighborhood":"Savassi",
      "city":"Belo Horizonte"
    }
  }'
```

### 3. Postman

1. **Método**: POST
2. **URL**: `https://lojadaesquina.store/webhook/chaves-na-mao`
3. **Headers**:
   - `Content-Type`: `application/json`
   - `Authorization`: `Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw`
4. **Body** (raw, JSON):
```json
{
  "id": "TEST001",
  "name": "João Silva",
  "phone": "31987654321",
  "email": "joao@example.com",
  "segment": "REAL_ESTATE",
  "ad": {
    "type": "Apartamento",
    "rooms": 3,
    "suites": 2,
    "garages": 2,
    "price": 500000,
    "neighborhood": "Savassi",
    "city": "Belo Horizonte"
  }
}
```

## 📊 Verificar Lead no Banco

```bash
# Via SSH no servidor
mysql -u u815655858_saas -p'MundoMelhor@10' u815655858_saas \
  -e "SELECT id, nome, telefone, email, quartos, suites, garagem, budget_max FROM leads ORDER BY id DESC LIMIT 5;"
```

## 🔍 Monitorar Logs

```bash
# Em tempo real
ssh -p 65002 u815655858@145.223.105.168
cd domains/lojadaesquina.store/public_html
tail -f storage/logs/lumen-$(date +%Y-%m-%d).log | grep -E "(📥|Chaves)"
```

## 🧪 Payloads de Teste

### Imóvel Completo
```json
{
  "id": "IMO123",
  "name": "Carlos Pereira",
  "phone": "21987654321",
  "email": "carlos@email.com",
  "message": "Interessado no apartamento",
  "segment": "REAL_ESTATE",
  "ad": {
    "type": "Apartamento",
    "rooms": 3,
    "suites": 2,
    "garages": 2,
    "price": 650000,
    "neighborhood": "Leblon",
    "city": "Rio de Janeiro"
  }
}
```

### Veículo
```json
{
  "id": "VEI456",
  "name": "Ana Costa",
  "phone": "11976543210",
  "email": "ana@email.com",
  "message": "Quero saber sobre o carro",
  "segment": "VEHICLE",
  "ad": {
    "brand": "Toyota",
    "model": "Corolla",
    "year": 2023,
    "price": 120000
  }
}
```

### Mínimo (sem dados opcionais)
```json
{
  "id": "MIN789",
  "name": "Pedro Lima",
  "segment": "REAL_ESTATE"
}
```

## ⚠️ Tratamento de Erros

### Sem Autenticação
```bash
curl -X POST https://lojadaesquina.store/webhook/chaves-na-mao \
  -H "Content-Type: application/json" \
  -d '{"id":"TEST","name":"Teste"}'
```
**Resposta esperada**: `401 Unauthorized`

### Autenticação Inválida
```bash
curl -X POST https://lojadaesquina.store/webhook/chaves-na-mao \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic CREDENCIAL_ERRADA" \
  -d '{"id":"TEST","name":"Teste"}'
```
**Resposta esperada**: `401 Credenciais inválidas`

## 📋 Checklist de Validação

- [x] Webhook recebe requisição POST
- [x] Valida autenticação Basic Auth
- [x] Processa payload JSON
- [x] Mapeia campos do anúncio (quartos, suítes, garagem, preço)
- [x] Cria lead no banco com dados corretos
- [x] Retorna JSON de sucesso
- [x] Registra logs auditáveis
- [x] Trata campos opcionais (email, telefone)
- [x] Funciona com segment REAL_ESTATE
- [ ] Funciona com segment VEHICLE (não testado)

## 🔐 Credenciais

- **Email**: `contato@exclusivalarimoveis.com.br`
- **Token**: `d825c542e26df27c9fe696c391ee590`
- **Basic Auth (Base64)**: `Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw`

**Como gerar Basic Auth:**
```bash
echo -n "contato@exclusivalarimoveis.com.br:d825c542e26df27c9fe696c391ee590" | base64
```

## 📌 Próximos Passos

1. ✅ Webhook implementado e testado
2. ⏳ Configurar URL no painel Chaves na Mão
3. ⏳ Aguardar lead real para validação
4. ⏳ Testar segment VEHICLE
5. ⏳ Remover código antigo (ChavesNaMaoService, LeadObserver)

## 🆘 Suporte

- **Documentação**: `docs/INTEGRACAO_CHAVES_NA_MAO.md`
- **Logs**: `storage/logs/lumen-YYYY-MM-DD.log`
- **Webhook URL**: `https://lojadaesquina.store/webhook/chaves-na-mao`
