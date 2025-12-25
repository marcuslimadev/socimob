# Configuração de Variáveis de Ambiente por Tenant

## Visão Geral

A partir desta versão, as configurações sensíveis de API, tokens e SMTP não podem mais ser gerenciadas através do painel administrativo do tenant. Essas configurações devem ser definidas por meio de variáveis de ambiente no arquivo `.env`.

Esta mudança visa:
- **Segurança**: Evitar exposição acidental de chaves sensíveis
- **Controle**: Manter o controle centralizado das credenciais
- **Prevenção de Erros**: Evitar remoção ou digitação incorreta por parte dos administradores

## Padrão de Nomenclatura

As variáveis de ambiente seguem o padrão:

```
TENANT_{ID}_{CONFIGURACAO}
```

Onde:
- `{ID}` é o ID numérico do tenant no banco de dados
- `{CONFIGURACAO}` é o nome da configuração específica

## Configurações Disponíveis

### Chaves de API

```bash
# Pagar.me
TENANT_1_PAGAR_ME_KEY=sk_test_xxxxxxxxxxxxx

# APM Imóveis
TENANT_1_APM_IMOVEIS_KEY=api_key_xxxxxxxxxxxxx

# NECA
TENANT_1_NECA_KEY=neca_xxxxxxxxxxxxx

# OpenAI
TENANT_1_OPENAI_KEY=sk-xxxxxxxxxxxxx
```

### Twilio WhatsApp

```bash
# Twilio Account SID
TENANT_1_TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxx

# Twilio Auth Token
TENANT_1_TWILIO_AUTH_TOKEN=xxxxxxxxxxxxx

# Twilio WhatsApp From Number
TENANT_1_TWILIO_WHATSAPP_FROM=whatsapp:+5531999999999
```

### Configurações SMTP

```bash
# SMTP Host
TENANT_1_SMTP_HOST=smtp.gmail.com

# SMTP Port
TENANT_1_SMTP_PORT=587

# SMTP Username
TENANT_1_SMTP_USERNAME=seu-email@gmail.com

# SMTP Password
TENANT_1_SMTP_PASSWORD=sua-senha-de-app

# SMTP From Email
TENANT_1_SMTP_FROM_EMAIL=noreply@seudominio.com

# SMTP From Name
TENANT_1_SMTP_FROM_NAME=Nome da Imobiliária
```

## Exemplo Completo de Configuração

Para um tenant com `id = 1`, adicione ao arquivo `.env`:

```bash
# ====================================
# TENANT 1 - Imobiliária Exemplo
# ====================================

# APIs de Terceiros
TENANT_1_PAGAR_ME_KEY=sk_test_xxxxxxxxxxxxx
TENANT_1_APM_IMOVEIS_KEY=api_key_xxxxxxxxxxxxx
TENANT_1_NECA_KEY=neca_xxxxxxxxxxxxx
TENANT_1_OPENAI_KEY=sk-xxxxxxxxxxxxx

# WhatsApp/Twilio
TENANT_1_TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxx
TENANT_1_TWILIO_AUTH_TOKEN=xxxxxxxxxxxxx
TENANT_1_TWILIO_WHATSAPP_FROM=whatsapp:+5531999999999

# SMTP
TENANT_1_SMTP_HOST=smtp.gmail.com
TENANT_1_SMTP_PORT=587
TENANT_1_SMTP_USERNAME=contato@imobiliariaexemplo.com.br
TENANT_1_SMTP_PASSWORD=app-password-here
TENANT_1_SMTP_FROM_EMAIL=noreply@imobiliariaexemplo.com.br
TENANT_1_SMTP_FROM_NAME=Imobiliária Exemplo
```

## Múltiplos Tenants

Para configurar múltiplos tenants, repita o padrão para cada ID:

```bash
# Tenant 1
TENANT_1_OPENAI_KEY=sk-tenant1-key
TENANT_1_TWILIO_ACCOUNT_SID=AC-tenant1-sid

# Tenant 2
TENANT_2_OPENAI_KEY=sk-tenant2-key
TENANT_2_TWILIO_ACCOUNT_SID=AC-tenant2-sid

# Tenant 3
TENANT_3_OPENAI_KEY=sk-tenant3-key
TENANT_3_TWILIO_ACCOUNT_SID=AC-tenant3-sid
```

## Fallback para Banco de Dados

Se uma variável de ambiente não estiver definida, o sistema tentará buscar o valor do banco de dados como fallback. Isso garante compatibilidade com configurações existentes, mas recomenda-se migrar todas as configurações sensíveis para variáveis de ambiente.

## Como Descobrir o ID do Tenant

Você pode descobrir o ID do tenant de várias formas:

### 1. Via Banco de Dados
```sql
SELECT id, name, domain FROM tenants;
```

### 2. Via Logs
O ID do tenant aparece nos logs do sistema quando o tenant faz login ou realiza operações.

### 3. Via API (Super Admin)
```bash
GET /api/super-admin/tenants
```

## Mudanças na Interface Administrativa

### O que foi removido:
- ❌ Aba "Integrações" no painel de configurações
- ❌ Formulários de configuração de Twilio
- ❌ Formulários de configuração de OpenAI
- ❌ Formulários de configuração de API Externa
- ❌ Campos `api_url_externa` e `api_token_externa` no formulário de empresa

### O que permanece:
- ✅ Configurações de Perfil
- ✅ Configurações de Empresa (nome, logo, cores, etc.)
- ✅ Configurações de Segurança (troca de senha)

### Endpoints da API Modificados

Os seguintes endpoints agora retornam erro 403 (Forbidden):
- `PUT /api/admin/settings/api-keys` 
- `PUT /api/admin/settings/email`

Mensagem de erro retornada:
```json
{
  "error": "Forbidden",
  "message": "As configurações de API agora são gerenciadas via variáveis de ambiente. Entre em contato com o desenvolvedor para atualizar estas configurações."
}
```

## Segurança

### ⚠️ IMPORTANTE

1. **Nunca commite o arquivo `.env`** no controle de versão
2. Mantenha o `.env` no `.gitignore`
3. Use senhas de aplicativo específicas para SMTP (não use sua senha pessoal)
4. Rotacione as chaves de API periodicamente
5. Restrinja acesso ao servidor apenas para desenvolvedores autorizados

## Suporte

Se você é um administrador de tenant e precisa alterar suas configurações de API ou SMTP, entre em contato com o desenvolvedor/suporte técnico responsável pelo sistema.

Se você é um desenvolvedor e precisa adicionar/modificar configurações:
1. Acesse o servidor via SSH
2. Edite o arquivo `.env` na raiz do projeto
3. Adicione ou modifique as variáveis seguindo o padrão `TENANT_{ID}_{CONFIG}`
4. Reinicie o servidor PHP se necessário
