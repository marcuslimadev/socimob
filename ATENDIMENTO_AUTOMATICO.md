# Atendimento Automático via WhatsApp Template

## Visão Geral

Quando um lead é criado (de qualquer fonte: portal, WhatsApp, importação, etc.), o sistema automaticamente:

1. ✅ Cria uma conversa no sistema
2. ✅ Envia uma mensagem de boas-vindas aprovada via WhatsApp Template
3. ✅ Registra a mensagem na conversa
4. ✅ Atualiza o status do lead para "em_atendimento"

## Configuração por Tenant

Cada tenant pode ter seu próprio template aprovado do WhatsApp configurado no arquivo `.env`.

### Formato da Variável

```bash
{SLUG_DO_TENANT}_TENANT_TWILIO_TEMPLATE_WELCOME_SID=HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Exemplos

#### Exclusiva Lar Imóveis
```bash
EXCLUSIVA_TENANT_TWILIO_TEMPLATE_WELCOME_SID=HX4f61c2b07ceef4afc402a9c4753300df
```

#### Outro Tenant (exemplo)
```bash
TENANT_1_TWILIO_TEMPLATE_WELCOME_SID=HXyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy
```

## Como Obter o Template SID

1. Acesse o [Twilio Console](https://console.twilio.com)
2. Vá em **Messaging** → **Content Editor**
3. Crie ou selecione o template de boas-vindas
4. **Submeta o template para aprovação** (obrigatório para WhatsApp)
5. Aguarde aprovação do WhatsApp (pode levar algumas horas)
6. Copie o **Content SID** (começa com `HX...`)
7. Adicione no `.env` conforme formato acima

## Como o Sistema Determina o Tenant

O sistema tenta buscar o template SID usando múltiplas formas:

1. `{SLUG_MAIUSCULO}_TENANT_TWILIO_TEMPLATE_WELCOME_SID`
2. `TENANT_{ID}_TWILIO_TEMPLATE_WELCOME_SID`
3. `{SLUG_SANITIZADO}_TWILIO_TEMPLATE_WELCOME_SID`
4. Fallback: `TWILIO_TEMPLATE_WELCOME_SID` (global)

**Exemplo**: Para tenant com slug `exclusiva-lar-imoveis`:
- Tenta: `EXCLUSIVA_LAR_IMOVEIS_TENANT_TWILIO_TEMPLATE_WELCOME_SID`
- Tenta: `EXCLUSIVA_LAR_IMOVEIS_TWILIO_TEMPLATE_WELCOME_SID`
- Fallback: `TWILIO_TEMPLATE_WELCOME_SID`

## Variáveis do Template

Os templates podem usar variáveis dinâmicas:

```
{{1}} = Nome do lead
{{2}} = Tipo de interesse (ex: "apartamentos")
```

### Exemplo de Template

```
Olá {{1}}! 👋

Somos da Exclusiva Lar Imóveis e vi que você tem interesse em {{2}}.

Meu nome é Teresa, posso te ajudar a encontrar o imóvel perfeito! Quando seria um bom momento para conversarmos? 🏠
```

## Ativar/Desativar Atendimento Automático

O atendimento automático está **ATIVO por padrão** para todos os tenants.

Para desativar para um tenant específico:

```php
use App\Models\AppSetting;

// Desativar
AppSetting::setValue('atendimento_automatico_ativo', false, $tenantId);

// Reativar
AppSetting::setValue('atendimento_automatico_ativo', true, $tenantId);
```

## Logs e Monitoramento

Todos os envios são logados:

```bash
[LeadObserver] Novo lead criado
[LeadObserver] Iniciando atendimento IA automático
[LeadAutomation] Template SID encontrado
[LeadAutomation] Template WhatsApp enviado
[LeadObserver] Atendimento IA iniciado com sucesso
```

Verifique os logs em `storage/logs/lumen-YYYY-MM-DD.log`

## Fallback

Se o template não estiver configurado ou falhar:
- ✅ Sistema gera mensagem personalizada via OpenAI
- ✅ Envia mensagem normal (não-template) via Twilio
- ✅ Atendimento continua normalmente

## Requisitos

- ✅ Lead deve ter `telefone` ou `whatsapp` preenchido
- ✅ Template deve estar aprovado no WhatsApp
- ✅ Twilio deve estar configurado para o tenant
- ✅ `atendimento_automatico_ativo` não pode estar explicitamente desativado

## Troubleshooting

### Template não está sendo enviado

1. Verifique se o SID está correto no `.env`
2. Verifique se o template foi aprovado no WhatsApp
3. Verifique os logs: `tail -f storage/logs/lumen-$(date +%Y-%m-%d).log | grep LeadAutomation`
4. Teste manualmente:
```bash
curl -X POST https://seusite.com/api/leads \
  -H "Authorization: Bearer TOKEN" \
  -d '{"nome":"Teste","telefone":"5531999999999"}'
```

### Mensagem genérica ao invés do template

Isso significa que:
- Template SID não está configurado no `.env`, ou
- Template começa com `HX...` (placeholder), ou
- Falha no envio do template (veja logs)

Sistema automaticamente usa fallback com mensagem IA personalizada.

## Arquivos Envolvidos

- `app/Observers/LeadObserver.php` - Detecta criação de lead
- `app/Services/LeadAutomationService.php` - Envia template/mensagem
- `app/Services/TwilioService.php` - Integração com Twilio
- `.env` - Configuração dos templates por tenant
