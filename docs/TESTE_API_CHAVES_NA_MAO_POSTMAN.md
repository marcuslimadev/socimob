# 🧪 Guia de Teste - API Chaves na Mão via Postman

## Configuração da Requisição

### 1. Informações Básicas

**URL Base (presumida):**
```
https://api.chavesnamao.com.br/leads
```

**Método:** `POST`

**Credenciais:**
- Email: `contato@exclusivalarimoveis.com.br`
- Token: `d825c542e26df27c9fe696c391ee590`

---

## Passo a Passo no Postman

### 1️⃣ Criar Nova Requisição

1. Abra o Postman
2. Clique em **New** → **HTTP Request**
3. Nomeie: `Chaves na Mão - Criar Lead`

### 2️⃣ Configurar URL e Método

- **Method:** `POST`
- **URL:** `https://api.chavesnamao.com.br/leads`

### 3️⃣ Configurar Headers

Na aba **Headers**, adicione:

| Key | Value |
|-----|-------|
| `Content-Type` | `application/json` |
| `Accept` | `application/json` |
| `Authorization` | `Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw` |

**Como gerar o valor do Authorization:**

O valor `Basic Y29udGF0b0B...` é gerado assim:

1. Concatenar: `email:token`
   ```
   contato@exclusivalarimoveis.com.br:d825c542e26df27c9fe696c391ee590
   ```

2. Converter para Base64:
   - Online: https://www.base64encode.org/
   - Resultado: `Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw`

3. Adicionar prefixo: `Basic `

**Valor final do header Authorization:**
```
Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw
```

### 4️⃣ Configurar Body (JSON)

Na aba **Body**:
1. Selecione **raw**
2. Selecione **JSON** no dropdown

**Payload de Teste:**

```json
{
  "nome": "João Silva",
  "email": "joao.silva@example.com",
  "telefone": "31999887766",
  "origem": "Exclusiva SaaS",
  "status": "novo",
  "observacoes": "Cliente interessado em apartamentos",
  "orcamento": "R$ 300.000,00 - R$ 500.000,00",
  "localizacao": "Pampulha, Belo Horizonte",
  "quartos": 3,
  "referencia_externa": "EXCLUSIVA_LEAD_TEST_001"
}
```

### 5️⃣ Enviar Requisição

1. Clique em **Send**
2. Aguarde resposta (pode demorar até 30s)

---

## Interpretando Respostas

### ✅ Sucesso (200-299)

**Status Code:** `200`, `201`, `204`

**Exemplo de Resposta:**
```json
{
  "success": true,
  "message": "Lead criado com sucesso",
  "data": {
    "id": 12345,
    "nome": "João Silva",
    "created_at": "2025-12-26T01:30:00Z"
  }
}
```

**Ação:** Integração está correta! ✅

---

### ❌ Erro 401 - Não Autorizado

**Motivo:** Credenciais incorretas (email ou token inválido)

**Exemplo:**
```json
{
  "error": "Unauthorized",
  "message": "Invalid credentials"
}
```

**Ações:**
1. Verificar email: `contato@exclusivalarimoveis.com.br`
2. Verificar token: `d825c542e26df27c9fe696c391ee590`
3. Confirmar com Chaves na Mão se credenciais estão ativas
4. Verificar se token expirou

---

### ❌ Erro 404 - Não Encontrado

**Motivo:** URL incorreta

**Possíveis URLs corretas:**
- `https://api.chavesnamao.com.br/api/leads`
- `https://api.chavesnamao.com.br/v1/leads`
- `https://chavesnamao.com.br/api/leads`
- `https://app.chavesnamao.com.br/api/leads`

**Ação:** Solicitar documentação oficial da API ao Chaves na Mão

---

### ❌ Erro 422 - Unprocessable Entity

**Motivo:** Payload com formato incorreto ou campos obrigatórios faltando

**Exemplo:**
```json
{
  "error": "Validation failed",
  "errors": {
    "email": ["O campo email é obrigatório"],
    "telefone": ["O telefone deve ter formato válido"]
  }
}
```

**Ação:** Ajustar payload conforme erros retornados

---

### ❌ Erro 502 - Bad Gateway (atual)

**Motivo:** 
- Servidor fora do ar
- URL incorreta redirecionando para proxy inválido
- Cloudflare ou firewall bloqueando

**Ação:**
1. Testar URLs alternativas
2. Verificar se API está online
3. Contatar suporte do Chaves na Mão

---

## Testes Alternativos

### Teste 1: Verificar se domínio resolve

Abra terminal e execute:

```bash
ping api.chavesnamao.com.br
```

**Esperado:** IP válido respondendo

**Se falhar:** Domínio não existe ou está offline

### Teste 2: Testar HTTPS direto

No navegador, abra:
```
https://api.chavesnamao.com.br
```

**Possíveis resultados:**
- ✅ Página com documentação da API
- ✅ JSON com mensagem de boas-vindas
- ❌ Erro SSL/certificado → Domínio incorreto
- ❌ Timeout → Servidor offline

### Teste 3: Testar com cURL (Linux/Mac)

```bash
curl -X POST https://api.chavesnamao.com.br/leads \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw" \
  -d '{
    "nome": "Teste API",
    "telefone": "31999999999",
    "email": "teste@example.com"
  }' \
  -v
```

### Teste 4: Testar com PowerShell (Windows)

```powershell
$headers = @{
    "Content-Type" = "application/json"
    "Authorization" = "Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkw"
}

$body = @{
    nome = "Teste API"
    telefone = "31999999999"
    email = "teste@example.com"
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://api.chavesnamao.com.br/leads" `
    -Method POST `
    -Headers $headers `
    -Body $body `
    -ContentType "application/json"
```

---

## Informações para Solicitar ao Chaves na Mão

Se os testes falharem, solicite ao fornecedor:

### 📋 Checklist de Informações

1. **URL completa da API de Leads**
   - Exemplo: `https://api.chavesnamao.com.br/v1/leads`

2. **Método de autenticação**
   - [ ] Basic Auth (email:token)
   - [ ] Bearer Token
   - [ ] API Key no header
   - [ ] Outro: _____________

3. **Formato do payload esperado**
   ```json
   {
     "campo1": "tipo",
     "campo2": "tipo"
   }
   ```

4. **Campos obrigatórios**
   - [ ] nome
   - [ ] email
   - [ ] telefone
   - [ ] outros: _____________

5. **Documentação da API**
   - Link: _____________
   - Exemplos de requisição

6. **Ambiente de teste (sandbox)**
   - URL: _____________
   - Credenciais de teste

7. **Status das credenciais fornecidas**
   - [ ] Ativas
   - [ ] Expiradas
   - [ ] Precisam ser ativadas

---

## Depois de Obter URL/Payload Corretos

### Atualizar no código:

1. Editar `app/Services/ChavesNaMaoService.php`:

```php
// Linha 13 - Alterar URL
private string $apiUrl = 'https://URL_CORRETA_AQUI/leads';
```

2. Se necessário, ajustar payload no método `buildPayload()`:

```php
// Linha ~151 - Ajustar campos conforme documentação
private function buildPayload(Lead $lead): array
{
    return [
        'campo_nome_correto' => $lead->nome,
        // ... ajustar conforme documentação
    ];
}
```

3. Deploy das alterações:

```bash
git add app/Services/ChavesNaMaoService.php
git commit -m "fix: Atualizar URL/payload da API Chaves na Mão"
git push origin master
```

4. No servidor:

```bash
cd domains/lojadaesquina.store/public_html
git pull origin master
curl "https://lojadaesquina.store/opcache_clear.php"
/opt/alt/php83/usr/bin/php artisan chaves:sync test
```

---

## Resumo Visual Postman

```
┌─────────────────────────────────────────────────────┐
│ POST  https://api.chavesnamao.com.br/leads          │
├─────────────────────────────────────────────────────┤
│ Headers:                                            │
│  Content-Type: application/json                     │
│  Authorization: Basic Y29udGF0b0Bl...               │
├─────────────────────────────────────────────────────┤
│ Body (raw JSON):                                    │
│  {                                                  │
│    "nome": "João Silva",                           │
│    "email": "joao@example.com",                    │
│    "telefone": "31999887766",                      │
│    "origem": "Exclusiva SaaS"                      │
│  }                                                 │
├─────────────────────────────────────────────────────┤
│ [Send] ───────────────────────────────────────────▶ │
└─────────────────────────────────────────────────────┘
```

Boa sorte! 🚀
