# Atualização: Captura Automática de Documentos e Status Inline

## Status: ✅ IMPLEMENTADO E DEPLOYED (Commit 6ae2a19)

## 🎯 Funcionalidades Implementadas

### 1. Captura Automática de Documentos do WhatsApp

#### Backend (`WhatsAppService.php`)
- **Tipos Suportados**: PDFs e Imagens (JPG, JPEG, PNG)
- **Detecção Inteligente**: 
  - Verifica MIME type da mensagem
  - Fallback para extensão do arquivo se MIME type não disponível
  - Suporta tanto `messageType === 'document'` quanto `messageType === 'image'`

#### Fluxo de Captura
1. Mensagem com mídia chega via webhook
2. Sistema detecta tipo (PDF ou imagem)
3. Extrai URL e metadata
4. Classifica documento baseado no conteúdo da mensagem:
   - `identificacao`: Se mensagem contém "CPF", "RG", "identidade"
   - `comprovante_renda`: Se contém "renda", "holerite", "contracheque"
   - `comprovante_endereco`: Se contém "endereço", "conta de luz", "conta de água"
   - `documento`: Padrão se não detectar categoria específica
5. Salva em `lead_documents` com:
   - `tenant_id`: Isolamento multi-tenant
   - `lead_id`: Vínculo com o lead
   - `conversa_id`: Rastreabilidade da conversa
   - `mensagem_id`: Link com a mensagem original
   - `nome`: Nome do arquivo extraído
   - `tipo`: Categoria detectada
   - `mime_type`: Tipo MIME
   - `arquivo_url`: URL do arquivo no Twilio
   - `status`: 'pendente' (aguardando revisão)

#### Feedback ao Usuário
- **PDF**: "📄 Recebi seu documento e já salvei no seu perfil! Um corretor pode revisar em breve. 😊"
- **Imagem**: "🖼️ Recebi seu imagem e já salvei no seu perfil! Um corretor pode revisar em breve. 😊"

### 2. Mudança de Status Inline no LeadCard

#### UI Components
- **Dropdown de Status**: Click no badge de status abre dropdown
- **Opções Disponíveis**:
  1. Novo
  2. Em Atendimento
  3. Qualificado
  4. Proposta
  5. Fechado
  6. Perdido

#### Funcionalidades
- Click no status abre dropdown sem abrir o perfil (stopPropagation)
- Click fora do dropdown fecha automaticamente (useEffect + refs)
- Atualização em tempo real:
  - PUT /api/leads/{id} com novo status
  - State local atualizado imediatamente
  - Toast de confirmação
  - Sem reload da página
- Visual feedback: item selecionado destacado

#### Comportamento
```typescript
// LeadCard.tsx
const [showStatusDropdown, setShowStatusDropdown] = useState(false);
const dropdownRef = useRef<HTMLDivElement>(null);

// Click outside to close
useEffect(() => {
  const handleClickOutside = (event: MouseEvent) => {
    if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
      setShowStatusDropdown(false);
    }
  };
  if (showStatusDropdown) {
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }
}, [showStatusDropdown]);
```

### 3. Tema Claro com Fundo Cinza

#### Antes
```css
--background: oklch(1 0 0); /* 100% branco */
```

#### Depois
```css
--background: oklch(0.96 0.001 286); /* 96% cinza claro */
```

#### Benefícios
- Menos cansativo visualmente
- Melhor contraste com cards brancos
- Mais moderno e profissional
- Alinhado com tendências de design 2026

## 📦 Estrutura de Dados

### LeadDocument Model
```php
protected $fillable = [
    'tenant_id',     // Isolamento multi-tenant
    'lead_id',       // Lead proprietário
    'conversa_id',   // Conversa de origem
    'mensagem_id',   // Mensagem que enviou
    'nome',          // Nome do arquivo
    'tipo',          // Categoria (identificacao, comprovante_renda, etc)
    'mime_type',     // application/pdf, image/jpeg, etc
    'arquivo_url',   // URL no storage (Twilio)
    'status',        // pendente, aprovado, rejeitado
];
```

## 🔄 Workflow Completo

### Cenário: Cliente envia RG via WhatsApp

1. **Cliente** (WhatsApp): Envia foto do RG com mensagem "Aqui está meu RG"

2. **Webhook** (Twilio → WhatsAppService):
   ```json
   {
     "from": "whatsapp:+5511999999999",
     "message": "Aqui está meu RG",
     "media_url": "https://api.twilio.com/...",
     "media_type": "image/jpeg"
   }
   ```

3. **WhatsAppService.processIncomingMessage()**:
   - Detecta `messageType = 'image'`
   - Valida MIME type: `image/jpeg` ✓
   - Cria Lead se não existir
   - Chama `handleIncomingDocument()`

4. **WhatsAppService.handleIncomingDocument()**:
   ```php
   $isValidImage = stripos($mediaType, 'image/jpeg') !== false;
   $tipo = $this->guessDocumentType("Aqui está meu RG");
   // Retorna: "identificacao"
   
   LeadDocument::create([
       'tenant_id' => 1,
       'lead_id' => 123,
       'conversa_id' => 456,
       'mensagem_id' => 789,
       'nome' => 'rg.jpg',
       'tipo' => 'identificacao',
       'mime_type' => 'image/jpeg',
       'arquivo_url' => 'https://api.twilio.com/...',
       'status' => 'pendente'
   ]);
   ```

5. **Feedback**: Cliente recebe "🖼️ Recebi seu imagem e já salvei no seu perfil!"

6. **Corretor**: Acessa `/leads/123`, vai na tab "Documentos", vê o RG listado

7. **Download ZIP**: Corretor clica "Baixar Todos (ZIP)", recebe `lead-123-documentos.zip`

### Cenário: Corretor muda status do lead

1. **Corretor** (Frontend): Na lista de leads, clica no status "Novo"

2. **Dropdown** aparece com opções:
   - Novo (atual)
   - Em Atendimento ← **seleciona**
   - Qualificado
   - Proposta
   - Fechado
   - Perdido

3. **handleStatusChange()**:
   ```typescript
   await api.put('/leads/123', { status: 'em_atendimento' });
   setLeads(prevLeads => 
     prevLeads.map(lead => 
       lead.id === '123' ? { ...lead, status: 'em_atendimento' } : lead
     )
   );
   toast.success('Status atualizado com sucesso');
   ```

4. **Visual**: Badge muda de azul (Novo) para ciano (Em Atendimento) instantaneamente

## 🧪 Como Testar

### Teste 1: Captura de Documento PDF
1. Envie mensagem WhatsApp com PDF anexo
2. Texto: "Meu comprovante de renda"
3. Verifique: Documento salvo como `comprovante_renda`
4. Acesse: `/leads/{id}` → Tab Documentos
5. Confirme: PDF listado e botão "Baixar Todos (ZIP)" disponível

### Teste 2: Captura de Imagem
1. Envie foto via WhatsApp
2. Texto: "Minha CNH"
3. Verifique: Documento salvo como `identificacao`
4. Confirme: Emoji 🖼️ no feedback

### Teste 3: Mudança de Status
1. Na lista de leads, clique no badge de status
2. Selecione novo status
3. Verifique: Toast de confirmação
4. Confirme: Badge atualizado sem reload
5. Recarregue página: Status persistido

### Teste 4: Tema Claro
1. Alterne para modo claro
2. Verifique: Fundo cinza claro (não branco puro)
3. Confirme: Cards brancos com bom contraste

## 🚀 Deploy

### Commits
- `a940ac4` - Sistema de perfil com documentos
- `5d13e6f` - Documentação e scripts
- `6ae2a19` - **Captura automática e status inline** ✅ DEPLOYED

### Produção
- **URL**: https://lojadaesquina.store
- **API Health**: ✅ Online
- **Build**: Frontend compilado e copiado
- **Status**: Todas funcionalidades ativas

### Verificação
```bash
curl https://lojadaesquina.store/api/health
# Retorna: {"status":"ok","app":"SOCIMOB","version":"Lumen (10.0.4)"}
```

## 📝 Notas Técnicas

### Tipos MIME Suportados
- `application/pdf`
- `image/jpeg`
- `image/jpg`
- `image/png`

### Extensões de Fallback
- `.pdf`
- `.jpg`
- `.jpeg`
- `.png`

### Storage
- Documentos ficam no Twilio (URL externa)
- Não consomem espaço no servidor
- URL persiste na tabela `lead_documents`
- Download ZIP baixa de URLs externas e empacota

### Performance
- Detecção de tipo: O(1) - regex simples
- Salvamento: 1 INSERT query
- Feedback: Mensagem assíncrona
- Status update: 1 UPDATE query + optimistic UI

### Segurança
- Tenant isolation em todas queries
- Validação de MIME type antes de salvar
- Apenas PDFs e imagens aceitas
- URLs externas (Twilio) com autenticação

## 🔮 Próximos Passos

### Melhorias Documentos
- [ ] Preview inline de imagens
- [ ] Download individual de documentos
- [ ] Drag & drop para upload manual
- [ ] Editar tipo/categoria do documento
- [ ] Aprovação/rejeição com workflow
- [ ] OCR em documentos (extrair dados)

### Melhorias Status
- [ ] Automação de status (regras)
- [ ] Pipeline visual (kanban)
- [ ] Histórico de mudanças de status
- [ ] Notificações em mudanças
- [ ] Bulk update de status

### Analytics
- [ ] Tempo médio em cada status
- [ ] Taxa de conversão por status
- [ ] Documentos mais enviados
- [ ] Performance de corretores
