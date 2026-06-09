# Socimob Atendimento 360 - Extensão Chrome

Extensão Chrome Manifest V3 que funciona como assistente de produtividade para corretores imobiliários no WhatsApp Web.

## Funcionalidades

- **Login Seguro**: Autenticação no Socimob com token Bearer
- **Detecção de Conversa**: Identifica automaticamente o contato e telefone no WhatsApp Web
- **Vinculação de Lead**: Busca e vincula conversas a leads existentes
- **Resumo Comercial**: Registra resumos de negociação e próximas ações
- **Ações Rápidas**: Criar tarefas, agendar visitas e registrar propostas
- **Templates de Mensagem**: Acesso a templates prontos com botão de copiar
- **Consentimento**: Registro de consentimento do usuário para uso da extensão
- **Segurança**: Armazenamento seguro de tokens com expiração automática

## Arquitetura

### Arquivos Principais

- **manifest.json**: Configuração da extensão (permissões, scripts, ícones)
- **background.js**: Service Worker que gerencia sessões e eventos globais
- **content.js**: Script injetado no WhatsApp Web para detectar conversas
- **popup.html/js**: Interface de login e controle rápido
- **side-panel.html/js**: Painel lateral principal com funcionalidades
- **options.html/js**: Página de configurações e privacidade

### Fluxo de Autenticação

1. Usuário abre o popup da extensão
2. Insere URL do Socimob, email e senha
3. Extensão faz POST em `/api/login` para obter token
4. Token é armazenado em `chrome.storage.local` com expiração de 24h
5. Background service worker monitora expiração a cada 5 minutos

### Fluxo de Vinculação de Conversa

1. Usuário abre WhatsApp Web e seleciona uma conversa
2. Content script detecta nome e telefone do contato
3. Usuário busca lead no painel lateral
4. Ao selecionar um lead, extensão envia POST em `/api/extension/conversations/link`
5. Conversa é vinculada e timeline é atualizada no Socimob

## Instalação

### Desenvolvimento

1. Clone o repositório Socimob
2. Navegue até `chrome-extension/`
3. Abra `chrome://extensions/` no Chrome
4. Ative "Modo de desenvolvedor" (canto superior direito)
5. Clique em "Carregar extensão não empacotada"
6. Selecione a pasta `chrome-extension/public`

### Build para Produção

```bash
cd chrome-extension
npm install
npm run build
# Arquivo .zip será gerado em dist/
```

## Configuração da API

A extensão espera os seguintes endpoints no Socimob:

### Autenticação
- `POST /api/login` - Login e obtenção de token

### Leads
- `GET /api/extension/leads/search?q=` - Buscar leads
- `POST /api/extension/leads` - Criar novo lead

### Conversas
- `POST /api/extension/conversations/link` - Vincular conversa a lead
- `POST /api/conversations/{id}/summary` - Salvar resumo comercial
- `POST /api/conversations/{id}/tasks` - Criar tarefa
- `POST /api/conversations/{id}/visits` - Agendar visita
- `POST /api/conversations/{id}/proposals` - Registrar proposta

### Templates
- `GET /api/extension/message-templates` - Listar templates de mensagem

### Consentimento
- `POST /api/extension/consent` - Registrar consentimento

## Segurança

### Boas Práticas Implementadas

1. **Tokens Bearer**: Uso de tokens Bearer para autenticação
2. **Armazenamento Seguro**: Tokens armazenados em `chrome.storage.local` (não em localStorage)
3. **Expiração Automática**: Sessões expiram após 24h ou inatividade
4. **Validação de Tenant**: API valida tenant_id do usuário
5. **Sem Captura Automática**: Extensão requer ação explícita do usuário
6. **Sanitização**: Texto do WhatsApp Web é sanitizado antes de enviar

### Restrições Implementadas

- ❌ Não captura conversas silenciosamente
- ❌ Não lê histórico completo automaticamente
- ❌ Não envia mensagens sem ação do usuário
- ❌ Não faz disparos em massa
- ❌ Não intercepta conversas pessoais sem vínculo comercial

## Desenvolvimento

### Estrutura de Pastas

```
chrome-extension/
├── public/
│   ├── manifest.json
│   ├── popup.html
│   ├── side-panel.html
│   ├── options.html
│   └── icons/
├── src/
│   ├── background.js
│   ├── content.js
│   ├── popup.js
│   ├── side-panel.js
│   └── options.js
└── README.md
```

### Comunicação entre Scripts

- **Background ↔ Content**: `chrome.tabs.sendMessage()`
- **Background ↔ Popup/Side Panel**: `chrome.runtime.sendMessage()`
- **Storage**: `chrome.storage.local`

### Debugging

1. Abra `chrome://extensions/`
2. Clique em "Detalhes" da extensão
3. Clique em "Visualizar visualização de fundo" para logs do background
4. Use DevTools do Chrome (F12) no popup e side panel

## Testes

### Testes Manuais Recomendados

1. **Login**: Verificar autenticação com credenciais válidas/inválidas
2. **Busca de Leads**: Testar busca por nome, telefone, email
3. **Vinculação**: Vincular conversa a um lead e verificar no Socimob
4. **Resumo**: Salvar resumo e verificar timeline
5. **Expiração**: Aguardar 24h ou limpar storage para testar relogin
6. **Segurança**: Verificar que dados de outro tenant não são acessíveis

## Troubleshooting

### Extensão não aparece no WhatsApp Web
- Verifique se a URL é `https://web.whatsapp.com`
- Recarregue a página (F5)
- Verifique permissões em `chrome://extensions/`

### Erro "Sessão expirada"
- Clique no popup e reconecte
- Verifique se o token foi salvo em `chrome.storage.local`

### Conversa não é detectada
- Verifique seletores CSS em `content.js` (podem variar por versão do WhatsApp)
- Abra DevTools (F12) e inspecione elementos

## Licença

Propriedade do Socimob. Todos os direitos reservados.
