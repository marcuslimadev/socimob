# E-mail de Registro — OLX Autoupload API

**Para:** integracao@olx.com.br / parceiros@olx.com.br  
**Assunto:** Solicitação de Registro de Aplicação — Protocolo de Autenticação OLX Autoupload API

---

Prezada equipe OLX,

Gostaria de solicitar o registro da aplicação abaixo para início do protocolo de autenticação com o servidor `olx.com.br`, visando a integração com a API Autoupload para publicação automatizada de anúncios imobiliários.

---

## Dados da Empresa

| Campo | Valor |
|-------|-------|
| **Razão Social / Nome** | SOCIMOB |
| **Website** | https://socimob.com |
| **E-mail de contato técnico** | marcus.lima@hotmail.com.br |
| **E-mail de notificações** | alert@socimob.com |
| **Telefone** | +55 (31) 9 0000-0000 |

---

## Dados da Aplicação

| Campo | Valor |
|-------|-------|
| **Nome da aplicação** | SOCIMOB Plataforma Imobiliária |
| **Versão** | 2.0 |
| **Tipo** | SaaS B2B multi-tenant |
| **Ambiente de produção** | https://app.socimob.com |
| **API de produção** | https://api.socimob.com |

**Descrição da aplicação:**

SOCIMOB é uma plataforma SaaS imobiliária multi-tenant voltada para a gestão completa de imobiliárias e corretores autônomos. A plataforma oferece CRM de leads, cadastro e publicação automática de imóveis em múltiplos portais, gestão financeira, automação de marketing e integrações via API.

A integração com a OLX utilizará a API Autoupload para:
- Publicação automática de imóveis cadastrados pelos clientes (tenants) da plataforma;
- Sincronização de status de anúncios (ativo, pausado, encerrado);
- Remoção de anúncios ao marcar imóvel como vendido/locado no CRM;
- **Captura de leads gerados pelos anúncios OLX via `GET /autoupload/v2/leads` (pull periódico)** diretamente no CRM da plataforma.

Cada tenant (imobiliária) autenticará individualmente com suas próprias credenciais OLX Pro. A plataforma não compartilha credenciais entre tenants.

---

## URIs de Redirecionamento OAuth

```
https://app.socimob.com/api/oauth/olx/callback
https://api.socimob.com/api/oauth/olx/callback
http://localhost:5173/oauth/olx/callback
http://localhost:8000/api/oauth/olx/callback
```

---

## Escopo e Permissões Necessárias

| Escopo | Finalidade |
|--------|-----------|
| `autoupload` | Publicação e gestão de anúncios de imóveis |
| `leads` | Leitura de leads gerados pelos anúncios (pull-based via `GET /autoupload/v2/leads`) |

---

## Informações Técnicas

| Item | Detalhe |
|------|---------|
| **Fluxo de autenticação** | OAuth 2.0 — `client_credentials` (por tenant) |
| **Endpoint de token** | `https://auth.olx.com.br/oauth/token` |
| **API utilizada** | Autoupload v2 — `https://apps.olx.com.br/autoupload/v2` |
| **Endpoint de leads** | `GET https://apps.olx.com.br/autoupload/v2/leads` (pull periódico) |
| **Volume estimado** | 100–5.000 anúncios/mês (crescimento gradual) |
| **Categorias** | Imóveis — Venda e Aluguel (residencial e comercial) |
| **Formato dos IDs** | `soci_{tenant_id}_{imovel_id}` (único por anúncio) |

---

## Ambiente de Homologação / Testes

Para testes iniciais utilizaremos o domínio de homologação abaixo, que poderá ser adicionado às URIs permitidas:

```
https://lojadaesquina.store/api/oauth/olx/callback
```

---

Fico no aguardo das credenciais de acesso (`client_id` e `client_secret`) e de qualquer orientação adicional para a continuidade do processo de homologação e posterior entrada em produção.

Atenciosamente,

**Marcus Lima**  
Responsável Técnico — SOCIMOB  
marcus.lima@hotmail.com.br  
https://socimob.com
