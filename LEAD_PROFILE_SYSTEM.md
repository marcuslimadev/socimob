# Sistema de Perfil de Leads com Documentos

## Status: ✅ IMPLEMENTADO (Commit a940ac4)

## Funcionalidades

### 1. Perfil Completo do Lead
- **Rota**: `/leads/:id`
- **Componente**: `client/src/pages/LeadProfile.tsx`
- **Tabs**:
  - ✅ **Informações**: Dados básicos do lead (nome, email, telefone, origem, status, data de cadastro)
  - ✅ **Documentos**: Upload, listagem, exclusão e download em ZIP
  - 🚧 **Intenções**: Placeholder (em desenvolvimento)
  - 🚧 **Atividades**: Placeholder (em desenvolvimento)

### 2. Gestão de Documentos

#### Backend (JÁ EXISTENTE)
- **Model**: `app/Models/LeadDocument.php`
- **Controller**: `app/Http/Controllers/LeadDocumentsController.php`
- **Service**: `app/Services/LeadDocumentService.php`
- **Migration**: `database/migrations/2025_12_19_000200_create_lead_support_tables.php`

#### Endpoints Disponíveis
```
GET    /api/leads/{id}/documents          - Listar documentos
POST   /api/leads/{id}/documents          - Upload de arquivo
DELETE /api/leads/{id}/documents/{docId}  - Excluir documento
GET    /api/leads/{id}/documents/export   - Download ZIP de todos documentos
```

#### Tipos de Arquivo Aceitos
- PDF (`.pdf`)
- Word (`.doc`, `.docx`)
- Imagens (`.jpg`, `.jpeg`, `.png`)

#### Campos do Documento
- `id`: ID único
- `tenant_id`: Tenant do lead
- `lead_id`: ID do lead
- `conversa_id`: ID da conversa (opcional)
- `mensagem_id`: ID da mensagem (opcional)
- `nome`: Nome do arquivo
- `tipo`: Tipo/categoria do documento
- `mime_type`: Tipo MIME (application/pdf, image/jpeg, etc)
- `arquivo_url`: Caminho no storage
- `status`: Status do documento (pendente, aprovado, rejeitado)
- `created_at`, `updated_at`: Timestamps

### 3. Navegação

#### LeadCard
- **Modificações**:
  - Adicionado prop `id` e `onClick`
  - Click no card abre o perfil (`/leads/{id}`)
  - Botões de ação (Chat, SMS, WhatsApp, Delete) usam `stopPropagation()`

#### Router
- Adicionada rota `/leads/:id` → `LeadProfile` component
- Importação do componente em `App.tsx`

### 4. UI/UX

#### Características
- Design responsivo (mobile-first)
- Dark mode support completo
- Animações com Framer Motion
- Tabs animadas com layout transitions
- Cards hover states
- Loading states
- Toast notifications (sonner)
- Icons do Lucide React

#### Componentes Visuais
- Header com nome do lead, email e telefone
- Botão de voltar
- Sistema de tabs com indicador ativo
- Upload via dropzone visual
- Lista de documentos com status colorido
- Botão de download ZIP (verde)
- Botão de upload (azul)
- Confirmação de exclusão

### 5. Estado Atual

#### ✅ Implementado
- Componente LeadProfile completo
- Integração com API backend
- Upload de documentos
- Listagem de documentos
- Exclusão de documentos
- Download ZIP de todos documentos
- Navegação via click no LeadCard
- Dark/Light mode support
- Animações e transições
- Error handling e feedback visual

#### 🚧 Pendente
- Tab "Intenções" (lead_property_matches table existe)
- Tab "Atividades" (atividades table existe)
- Preview de documentos inline
- Filtros de documentos por tipo/status
- Download individual de documentos
- Drag & drop upload
- Progress bar de upload

#### 📦 Infraestrutura Backend Existente
A tabela `lead_property_matches` e `atividades` já existem na migration:

```sql
-- lead_property_matches: relaciona leads com imóveis de interesse
CREATE TABLE lead_property_matches (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED,
    lead_id BIGINT UNSIGNED,
    imovel_id BIGINT UNSIGNED,
    score DECIMAL(5,2),
    status VARCHAR(50),
    observacoes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- atividades: histórico de interações
CREATE TABLE atividades (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED,
    lead_id BIGINT UNSIGNED,
    tipo VARCHAR(50),
    descricao TEXT,
    data_hora DATETIME,
    usuario_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Como Usar

### Acessar Perfil
1. Na página `/leads`, clique em qualquer card de lead
2. OU navegue diretamente para `/leads/{id}`

### Upload de Documento
1. Acesse o perfil do lead
2. Clique na tab "Documentos"
3. Clique em "Enviar Arquivo"
4. Selecione o arquivo (PDF, DOC, imagem)
5. Aguarde confirmação de upload

### Download ZIP
1. Acesse a tab "Documentos"
2. Se houver documentos, clique em "Baixar Todos (ZIP)"
3. Arquivo será baixado como `lead-{id}-documentos.zip`

### Excluir Documento
1. Na lista de documentos, clique no ícone de lixeira
2. Confirme a exclusão
3. Documento será removido do storage e banco

## Deployment

### Commit Atual
```
a940ac4 - feat: sistema de perfil de leads com documentos e download ZIP
```

### Arquivos Modificados
- `client/src/pages/LeadProfile.tsx` (NOVO)
- `client/src/App.tsx` (rota adicionada)
- `client/src/components/LeadCard.tsx` (id e onClick)
- `client/src/pages/Leads.tsx` (onClick handler)

### Scripts de Deploy
Execute quando a conexão estiver disponível:
```bash
# Linux/Mac
bash deploy-lead-profile.sh

# Windows
.\deploy-lead-profile.ps1
```

### Deploy Manual
```bash
ssh -p 65002 u815655858@145.223.105.168
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
pnpm install
pnpm build
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

## Próximos Passos

### Tab Intenções
- Controller para lead_property_matches
- UI para visualizar imóveis matched
- Sistema de score visual
- Adicionar/remover matches

### Tab Atividades
- Controller para atividades
- Timeline de interações
- Criar nova atividade manualmente
- Filtros por tipo de atividade

### Melhorias Documentos
- Preview inline (PDF viewer)
- Download individual
- Drag & drop upload
- Progress bar
- Filtros por tipo/status
- Categorização de documentos

## Notas Técnicas

### Performance
- Documentos são lazy-loaded
- ZIP é gerado on-demand e deletado após download
- Storage local em `storage/app/leads/{id}/documents/`

### Segurança
- Validação de MIME types no upload
- Tenant isolation em todas queries
- Autenticação requerida em todos endpoints
- Arquivos armazenados fora do public_html

### Compatibilidade
- React + TypeScript
- Wouter (não react-router)
- Lucide React (não react-icons)
- Sonner (não react-hot-toast)
- Framer Motion para animações
- TailwindCSS para estilização
