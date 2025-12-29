# 💰 Sistema Financeiro - Pagamento de Comissões

## Visão Geral

Sistema completo para pagamento de comissões de corretores com integração **Mercado Pago PIX** e emissão automática de **NFSe via NFE.io**.

## 🎯 Funcionalidades

### 1. Cálculo de Comissão
- ✅ Seleção de corretor
- ✅ Valor total da venda
- ✅ Percentual de comissão (0.01% a 100%)
- ✅ Cálculo automático em tempo real
- ✅ Exibição de dados bancários do corretor

### 2. Pagamento via PIX (Mercado Pago)
- ✅ Geração automática de QR Code PIX
- ✅ Código "Copia e Cola" para pagamento
- ✅ Verificação automática de status do pagamento (polling a cada 3 segundos)
- ✅ Confirmação instantânea quando pago

### 3. Emissão de NFSe (NFE.io)
- ✅ Emissão automática após confirmação do pagamento
- ✅ Armazenamento de número e PDF da NFSe
- ✅ Download direto da nota fiscal

### 4. Comprovantes
- ✅ Geração de comprovante de pagamento em PDF
- ✅ Dados completos da transação
- ✅ Histórico completo de comissões

## 📋 Configuração

### Variáveis de Ambiente (.env)

```env
# Mercado Pago
MERCADOPAGO_ACCESS_TOKEN=APP_USR-xxxx-xxxxxxxx-xxxx
MERCADOPAGO_BASE_URL=https://api.mercadopago.com

# NFE.io
NFE_IO_API_KEY=sua-api-key-aqui
NFE_IO_COMPANY_ID=sua-company-id-aqui
NFE_IO_SERVICE_CODE=01.01
```

### Como Obter Credenciais

#### Mercado Pago

1. Acesse: https://www.mercadopago.com.br/developers/panel/app
2. Crie uma aplicação ou use uma existente
3. Vá em "Credenciais"
4. Copie o **Access Token de Produção**
5. Cole em `MERCADOPAGO_ACCESS_TOKEN`

**Documentação:** https://www.mercadopago.com.br/developers/pt/docs/checkout-api/integration-configuration/integrate-with-pix

#### NFE.io

1. Acesse: https://app.nfe.io
2. Faça login ou crie uma conta
3. Vá em **Configurações** → **API**
4. Copie a **API Key**
5. Copie o **Company ID** (ID da empresa)
6. Configure o código de serviço conforme sua cidade

**Documentação:** https://nfe.io/docs/desenvolvedores/rest-api/nota-fiscal-servico

### Configuração do Webhook (Mercado Pago)

1. Acesse: https://www.mercadopago.com.br/developers/panel/app
2. Vá em "Webhooks"
3. Adicione a URL: `https://seu-dominio.com/api/webhooks/mercadopago`
4. Selecione eventos: "Pagamentos"

## 🗂️ Estrutura do Banco de Dados

### Tabela: `commissions`

```sql
id                      BIGINT          - ID único da comissão
tenant_id               BIGINT          - ID do tenant (imobiliária)
corretor_id             BIGINT          - ID do usuário corretor
valor_venda             DECIMAL(15,2)   - Valor total da venda
percentual              DECIMAL(5,2)    - Percentual da comissão
valor_comissao          DECIMAL(15,2)   - Valor calculado
status                  ENUM            - pendente, processando, pago, cancelado
observacoes             TEXT            - Observações opcionais

# Mercado Pago
mercadopago_payment_id  VARCHAR         - ID do pagamento no MP
mercadopago_qrcode      VARCHAR         - Código PIX copia e cola
mercadopago_qrcode_base64 TEXT          - QR Code em base64
pago_em                 TIMESTAMP       - Data/hora do pagamento

# Comprovante
comprovante_path        VARCHAR         - Caminho do PDF

# NFSe
nfe_io_id               VARCHAR         - ID da nota no NFE.io
nfse_numero             VARCHAR         - Número da NFSe
nfse_pdf_url            VARCHAR         - URL do PDF da NFSe
nfse_emitida_em         TIMESTAMP       - Data/hora de emissão
```

### Tabela: `users` (campos adicionados)

```sql
pix_key        VARCHAR   - Chave PIX do corretor
pix_type       ENUM      - cpf, cnpj, email, telefone, aleatoria
banco          VARCHAR   - Nome do banco
agencia        VARCHAR   - Agência bancária
conta          VARCHAR   - Número da conta
tipo_conta     ENUM      - corrente, poupanca
```

## 🚀 Como Usar

### 1. Acesso ao Sistema

```
http://127.0.0.1:8000/app/financeiro.html
```

- Login como **admin** ou **super_admin**
- Menu lateral: **Financeiro**

### 2. Cadastrar Dados Bancários do Corretor

**Via Interface (Configurações > Equipe):**
- Editar usuário corretor
- Adicionar chave PIX
- Informar banco/agência/conta (opcional)

**Via SQL Direto:**
```sql
UPDATE users 
SET pix_key = '11987654321',
    pix_type = 'telefone',
    banco = 'Banco Inter',
    agencia = '0001',
    conta = '12345678-9',
    tipo_conta = 'corrente'
WHERE id = 2;
```

### 3. Criar Nova Comissão

1. Selecione o corretor no dropdown
2. Digite o valor total da venda (ex: 500000,00)
3. Digite o percentual (ex: 6 para 6%)
4. Confira o valor calculado (R$ 30.000,00)
5. Verifique os dados bancários exibidos
6. Clique em "Pagar via PIX - Mercado Pago"

### 4. Realizar Pagamento

1. **QR Code** é gerado automaticamente
2. Abra o app do seu banco
3. Escaneie o QR Code **OU** copie o código PIX
4. Confirme o pagamento no banco
5. Sistema detecta automaticamente (polling 3s)
6. Status muda para **PAGO** ✅

### 5. NFSe Automática

- Após confirmação do pagamento
- NFSe é emitida automaticamente via NFE.io
- Número da nota aparece no comprovante
- PDF disponível para download

### 6. Histórico

- Todas as comissões aparecem no histórico
- Filtro por status (Pendente, Pago, Cancelado)
- Download de comprovantes
- Download de NFSe

## 🔄 Fluxo Completo

```
1. Admin cria comissão
   ↓
2. Backend calcula valor
   ↓
3. Mercado Pago gera PIX (QR Code + Código)
   ↓
4. Frontend exibe QR Code
   ↓
5. Admin faz pagamento no banco
   ↓
6. Polling verifica status (3 em 3s)
   ↓
7. Pagamento confirmado → Status: PAGO
   ↓
8. Backend emite NFSe automaticamente (NFE.io)
   ↓
9. Modal de sucesso com comprovante
   ↓
10. Histórico atualizado
```

## 📡 APIs Utilizadas

### Endpoints Criados

```
GET    /api/admin/corretores              - Lista corretores com dados bancários
POST   /api/admin/comissoes               - Cria comissão e gera PIX
GET    /api/admin/comissoes               - Lista histórico de comissões
GET    /api/admin/comissoes/{id}          - Detalhes de uma comissão
GET    /api/admin/comissoes/{id}/status   - Verifica status do pagamento
```

### Mercado Pago Integration

**Criar Pagamento PIX:**
```php
$mercadoPago->criarPagamentoPix([
    'transaction_amount' => 30000.00,
    'description' => 'Comissão - João Silva',
    'payer' => [
        'email' => 'joao@exemplo.com',
        'first_name' => 'João Silva'
    ]
]);

// Retorna: payment_id, qrcode, qrcode_base64
```

**Consultar Status:**
```php
$status = $mercadoPago->consultarPagamento($paymentId);
// Retorna: approved, pending, cancelled, etc
```

### NFE.io Integration

**Emitir NFSe:**
```php
$nfse = $nfeIO->emitirNFSe([
    'valorServicos' => 30000.00,
    'descricao' => 'Comissão sobre venda de imóvel',
    'tomador' => [
        'nome' => 'João Silva',
        'cpfCnpj' => '12345678900',
        'email' => 'joao@exemplo.com'
    ]
]);

// Retorna: id, numero, pdfUrl, xmlUrl
```

## 🧪 Testes

### Teste Local (Sem Credenciais)

```bash
# 1. Acessar interface
http://127.0.0.1:8000/app/financeiro.html

# 2. Criar comissão de teste
- Corretor: Selecione qualquer
- Valor: 100.000,00
- Percentual: 5
- Comissão calculada: R$ 5.000,00

# 3. Verificar erro amigável se não configurado
"Mercado Pago não configurado - configure MERCADOPAGO_ACCESS_TOKEN"
```

### Teste com Mercado Pago em Homologação

1. Use **Access Token de Teste** (não de produção)
2. Pagamentos de teste não são cobrados
3. Use CPFs/CNPJs de teste da documentação MP

### Verificar Logs

```bash
# Logs de comissões
tail -f backend/storage/logs/lumen-$(date +%Y-%m-%d).log | grep Commission

# Logs do Mercado Pago
tail -f backend/storage/logs/lumen-$(date +%Y-%m-% d).log | grep MercadoPago

# Logs do NFE.io
tail -f backend/storage/logs/lumen-$(date +%Y-%m-%d).log | grep "NFE.io"
```

## ⚠️ Troubleshooting

### Erro: "Mercado Pago não configurado"

**Solução:**
```bash
# Verificar .env
grep MERCADOPAGO .env

# Deve ter:
MERCADOPAGO_ACCESS_TOKEN=APP_USR-xxxx...
```

### QR Code não aparece

**Verificar:**
1. Console do navegador (F12)
2. Erros na chamada da API
3. Token de autenticação válido
4. Credenciais do Mercado Pago

### NFSe não é emitida

**Verificar:**
1. Logs: `grep "NFE.io" storage/logs/*.log`
2. Credenciais NFE_IO_API_KEY e NFE_IO_COMPANY_ID
3. Código de serviço da cidade configurado
4. CPF/CNPJ do corretor (obrigatório)

### Pagamento não detectado

**Verificar:**
1. Webhook configurado no Mercado Pago
2. URL pública acessível (use ngrok para teste local)
3. Polling está rodando (status atualiza a cada 3s)

## 📊 Relatórios

### Total de Comissões por Período

```sql
SELECT 
    COUNT(*) as total,
    SUM(valor_comissao) as valor_total,
    AVG(percentual) as percentual_medio
FROM commissions
WHERE status = 'pago'
  AND pago_em BETWEEN '2024-01-01' AND '2024-12-31';
```

### Top Corretores

```sql
SELECT 
    u.name,
    COUNT(c.id) as qtd_comissoes,
    SUM(c.valor_comissao) as total_recebido
FROM commissions c
JOIN users u ON u.id = c.corretor_id
WHERE c.status = 'pago'
GROUP BY u.id, u.name
ORDER BY total_recebido DESC
LIMIT 10;
```

### Comissões Pendentes

```sql
SELECT 
    c.id,
    u.name as corretor,
    c.valor_comissao,
    c.created_at
FROM commissions c
JOIN users u ON u.id = c.corretor_id
WHERE c.status IN ('pendente', 'processando')
ORDER BY c.created_at ASC;
```

## 🔒 Segurança

- ✅ Autenticação obrigatória (middleware `simple-auth`)
- ✅ Validação de tenant (corretor deve pertencer ao tenant)
- ✅ Logs completos de todas as operações
- ✅ Tokens de idempotência no Mercado Pago
- ✅ Validação de dados (valor mínimo, percentual válido)

## 📝 TODO / Melhorias Futuras

- [ ] Comprovante em PDF (gerar via DomPDF ou similar)
- [ ] Envio automático de comprovante por email
- [ ] Notificação por WhatsApp ao corretor
- [ ] Dashboard financeiro com gráficos
- [ ] Exportar relatório Excel/PDF
- [ ] Agendamento de pagamentos
- [ ] Parcelamento de comissões
- [ ] Integração com outros bancos (TED/DOC)
- [ ] Aprovação de comissões (workflow)

---

**Criado em:** 29/12/2024  
**Stack:** Lumen 10 + jQuery + Mercado Pago + NFE.io  
**Status:** ✅ Funcional e pronto para uso
