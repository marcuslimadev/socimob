# Compartilhamento de Imóveis Entre Tenants

## Visão Geral

Esta funcionalidade permite que um imóvel cadastrado para um tenant possa aparecer em múltiplos tenants **associados**. 

### Fluxo de Funcionamento

1. **Superadmin**: Cria associação entre tenants (ex: Exclusiva ↔ Maison) em Configurações > Associações de Tenants
2. **Admin do Tenant**: Ao cadastrar/editar um imóvel, pode marcar checkboxes para escolher quais tenants associados devem visualizar este imóvel
3. **Portal Público**: O imóvel aparece automaticamente no portal dos tenants selecionados

## Estrutura Técnica

### Tabelas
- **tenant_associations**: Associações diretas entre tenants (gerenciado pelo superadmin)
  - Criada/atualizada manualmente pelo superadmin
  - Exemplo: Exclusiva (ID 1) ↔ Maison (ID 2)
  
- **property_portal_tenants**: Relacionamento muitos-para-muitos entre imóveis e tenants
  - Determina quais imóveis aparecem em quais tenants
  - Gerenciado ao criar/atualizar imóvel

### Modelos
- **PropertyPortalTenant**: Modelo para armazenar compartilhamentos de imóveis
- **Property**: Relacionamento `portalTenants()`

### Controllers
- **SuperAdmin\PropertySharingController**: Para visualização/auditoria de compartilhamentos (apenas superadmin)
- **PropertyController**: Para gerenciar `portal_tenant_ids` ao criar/editar imóvel
- **Portal\PortalController**: Lê propriedades compartilhadas automaticamente

## Endpoints para Admin Cadastrar Imóvel

### 1. Obter Tenants Associados Disponíveis
```
GET /api/imoveis/portal-opcoes
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Exclusiva Imóveis",
      "domain": "exclusiva.com",
      "slug": "exclusiva",
      "is_owner": true
    },
    {
      "id": 2,
      "name": "Maison Imóveis",
      "domain": "maison.com",
      "slug": "maison",
      "is_owner": false
    }
  ]
}
```

**Uso no Frontend:**
```javascript
// Filtrar apenas tenants associados (excluindo o owner)
const associatedTenants = data.filter(t => !t.is_owner);
// Mostrar como checkboxes no formulário
```

### 2. Criar Imóvel com Compartilhamento
```
POST /api/imoveis
Content-Type: multipart/form-data

{
  "tipo_imovel": "apartamento",
  "finalidade_imovel": "venda",
  "titulo": "Apartamento 3 quartos",
  "valor_venda": 500000,
  "logradouro": "Rua Principal",
  "numero": "123",
  "cidade": "Brasília",
  "estado": "DF",
  "bairro": "Asa Sul",
  "cep": "70000-000",
  "dormitorios": 3,
  "banheiros": 2,
  "garagem": 1,
  "area_total": 120,
  "active": true,
  "exibir_imovel": true,
  "portal_tenant_ids": [2]  // IDs dos tenants associados que devem voir este imóvel
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 15,
    "tenant_id": 1,
    "codigo_imovel": "EXC-202600015",
    "titulo": "Apartamento 3 quartos",
    "portal_tenant_ids": [2],
    ...
  },
  "message": "Imóvel EXC-202600015 cadastrado com sucesso!"
}
```

### 3. Atualizar Compartilhamento de Imóvel Existente
```
PUT /api/imoveis/{id}
Content-Type: application/json

{
  "portal_tenant_ids": [2, 3]  // Novo conjunto de tenants associados
}
```

**Validações Aplicadas:**
- `portal_tenant_ids` inclui apenas tenants associados ao tenant atual
- Campo não pode incluir o ID do tenant **owner** do imóvel
- Se validação falhar, o campo é ignorado e mantém o valor anterior

### 4. Obter Detalhes de um Imóvel (com ids de compartilhamento)
```
GET /api/imoveis/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 15,
    "tenant_id": 1,
    "titulo": "Apartamento 3 quartos",
    "portal_tenant_ids": [2],
    ...
  }
}
```

## Endpoints SuperAdmin para Auditoria

### 1. Listar Todos os Compartilhamentos
```
GET /api/super-admin/property-sharing
```

**Query Parameters:**
- `property_id`: Filtrar por imóvel específico
- `tenant_id`: Filtrar por tenant que recebe compartilhamento
- `owner_tenant_id`: Imóveis compartilhados por um tenant específico

**Response:**
```json
{
  "success": true,
  "data": [...],
  "total": 45,
  "current_page": 1,
  "per_page": 50,
  "last_page": 1
}
```

### 2. Ver Detalhes de Compartilhamento de um Imóvel
```
GET /api/super-admin/property-sharing/{propertyId}/details
```

**Response:**
```json
{
  "success": true,
  "property": {
    "id": 15,
    "titulo": "Apartamento",
    "codigo": "EXC-202600015",
    "owner_tenant_id": 1
  },
  "owner_tenant": {
    "id": 1,
    "name": "Exclusiva Imóveis",
    "domain": "exclusiva.com"
  },
  "shared_with": [
    {
      "id": 1,
      "tenant_id": 2,
      "tenant": {
        "id": 2,
        "name": "Maison Imóveis",
        "domain": "maison.com",
        "slug": "maison"
      },
      "created_at": "2026-02-24T10:00:00Z"
    }
  ]
}
```

## Segurança

### Validações Implementadas

1. **Admins Normais**
   - Podem definir `portal_tenant_ids` apenas ao criar/editar imóvel
   - Apenas para tenants associados ao seu tenant
   - Não podem incluir seu próprio tenant

2. **PropertyController - Criação**
   - `portal_tenant_ids` inválidos são filtrados silenciosamente
   - Apenas tenants associados são aceitos
   - Tenant owner é automaticamente excluído

3. **PropertyController - Atualização**
   - Mesmas regras aplicadas
   - Se `portal_tenant_ids` não for fornecido, mantém valor anterior
   - Usuário não pode alterar imóvel de um tenant diferente

4. **Validação de Dados**
   - Imóvel deve existir
   - Tenants devem estar ativos
   - Associação entre tenants deve existir em `tenant_associations`
   - Validação de IDs únicos em `property_portal_tenants`

5. **Cache**
   - Cache de portal é invalidado para todos os tenants afetados
   - Garante que propriedades apareçam imediatamente
   - Usa chave: `portal_imoveis_tenant_{tenantId}`

## Fluxo Prático

### Exemplo: Exclusiva compartilha imóvel com Maison

#### 1. Superadmin cria associação entre tenants
```bash
# Na tela de Associações de Tenants (já existe)
# Seleciona: Exclusiva ↔ Maison
```

#### 2. Admin da Exclusiva cadastra um imóvel
```javascript
// GET /api/imoveis/portal-opcoes retorna:
[
  { id: 1, name: "Exclusiva", is_owner: true },
  { id: 2, name: "Maison", is_owner: false }
]

// Frontend mostra checkbox para Maison
// Admin marca: Aparecer também em "Maison"

// POST /api/imoveis com:
{
  "titulo": "Apartamento",
  ...
  "portal_tenant_ids": [2]
}
```

#### 3. Admin da Maison vê imóvel no portal
```bash
# Sem fazer nada! O imóvel aparece automaticamente
# GET /api/portal/imoveis retorna a propriedade 15
```

#### 4. Um tempo depois: Admin da Exclusiva edita imóvel
```javascript
// Decide remover de Maison
// PUT /api/imoveis/15 com:
{
  "portal_tenant_ids": []  // Remover de todos os tenants associados
}

// Imóvel deixa de aparecer em Maison
```

## Campos do Formulário de Imóvel

No formulário de criar/editar imóvel, adicionar seção:

```
┌─ Publicação em Portais ─────────────────┐
│                                         │
│ Aparece automaticamente em:             │
│ ☑ Exclusiva Imóveis (seu portal)        │
│                                         │
│ Aparecer também em:                     │
│ ☐ Maison Imóveis                        │
│ ☐ (Outros tenants associados)           │
│                                         │
└─────────────────────────────────────────┘
```

## Dados no Banco de Dados

### Exemplo: 2 tenants associados compartilhando 1 imóvel

**Tabela: tenant_associations**
```sql
id | tenant_id | associated_tenant_id | created_by | created_at
1  | 1         | 2                    | NULL       | 2026-02-20
2  | 2         | 1                    | NULL       | 2026-02-20
```

**Tabela: imo_properties**
```sql
id | tenant_id | codigo_imovel   | titulo            | ...
15 | 1         | EXC-202600015   | Apartamento...    | ...
```

**Tabela: property_portal_tenants**
```sql
id | property_id | tenant_id | created_at
1  | 15          | 2         | 2026-02-24
```

**Resultado:**
- Imóvel 15 pertence a Tenant 1 (Exclusiva)
- Imóvel 15 aparece no portal de Tenant 2 (Maison)
- Não aparece em mais nenhum outro tenant

## Restrições

- Tenant deve estar ativo para receber compartilhamento
- Imóvel deve estar ativo para aparecer no portal
- Não há limite de tenants para um imóvel
- Não há limite de imóveis que um tenant pode receber
- Um tenant não pode compartilhar imóvel consigo mesmo

## Logs

Ações registradas em logs:
- Criação de imóvel com `portal_tenant_ids`
- Atualização de `portal_tenant_ids`
- Tentativa de usar tenants não associados
- Cache invalidation para auditar performance
