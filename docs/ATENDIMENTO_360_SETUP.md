# Guia de Instalação e Configuração - Atendimento 360

## Pré-requisitos

- Laravel 11
- PHP 8.2+
- MySQL/MariaDB
- Node.js 18+
- Chrome/Chromium (para extensão)

## Instalação Backend

### 1. Executar Migrations

```bash
php artisan migrate
```

Isso criará todas as tabelas necessárias:
- `communication_channels`
- `crm_conversations`
- `crm_messages`
- `crm_conversation_events`
- `crm_conversation_tasks`
- `crm_conversation_visits`
- `crm_conversation_proposals`
- `extension_consent_logs`
- `extension_sessions`

### 2. Registrar Policies

Adicionar ao `AuthServiceProvider`:

```php
use App\Models\CrmConversation;
use App\Models\CrmMessage;
use App\Models\CrmConversationTask;
use App\Policies\CrmConversationPolicy;
use App\Policies\CrmMessagePolicy;
use App\Policies\CrmTaskPolicy;

protected $policies = [
    CrmConversation::class => CrmConversationPolicy::class,
    CrmMessage::class => CrmMessagePolicy::class,
    CrmConversationTask::class => CrmTaskPolicy::class,
];
```

### 3. Registrar Rotas

As rotas já estão adicionadas em `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'tenant'])->prefix('atendimento')->group(function () {
    // Rotas de atendimento
});

Route::middleware(['auth:sanctum', 'tenant'])->prefix('extension')->group(function () {
    // Rotas de extensão
});
```

### 4. Criar Usuário de Teste

```bash
php artisan tinker

$user = User::create([
    'name' => 'Maria Corretor',
    'email' => 'maria@example.com',
    'password' => bcrypt('password'),
    'tenant_id' => 1,
]);

$token = $user->createToken('api-token')->plainTextToken;
echo $token;
```

## Instalação Frontend

### 1. Importar Componentes no Projeto React

Os componentes estão em `resources/js/components/Atendimento360/`:
- `Inbox.jsx`
- `ConversationDetail.jsx`
- `Dashboard.jsx`
- `Settings.jsx`

### 2. Integrar com Roteador

```jsx
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Inbox from './components/Atendimento360/Inbox';
import ConversationDetail from './components/Atendimento360/ConversationDetail';
import Dashboard from './components/Atendimento360/Dashboard';
import Settings from './components/Atendimento360/Settings';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/atendimento" element={<Inbox />} />
        <Route path="/atendimento/:id" element={<ConversationDetail />} />
        <Route path="/atendimento/dashboard" element={<Dashboard />} />
        <Route path="/atendimento/settings" element={<Settings />} />
      </Routes>
    </Router>
  );
}
```

### 3. Configurar Cliente API

```jsx
const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api';
const TOKEN = localStorage.getItem('auth_token');

export const apiClient = {
  get: (endpoint) => fetch(`${API_BASE_URL}${endpoint}`, {
    headers: { 'Authorization': `Bearer ${TOKEN}` }
  }),
  post: (endpoint, data) => fetch(`${API_BASE_URL}${endpoint}`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${TOKEN}`, 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
};
```

## Instalação Extensão Chrome

### 1. Preparar Extensão para Desenvolvimento

```bash
cd chrome-extension
```

### 2. Carregar Extensão no Chrome

1. Abra `chrome://extensions/`
2. Ative "Modo de desenvolvedor" (canto superior direito)
3. Clique em "Carregar extensão não empacotada"
4. Selecione a pasta `chrome-extension/public`

### 3. Testar Extensão

1. Abra WhatsApp Web: `https://web.whatsapp.com`
2. Clique no ícone da extensão
3. Faça login com as credenciais do Socimob
4. Abra uma conversa e clique "Abrir Painel Lateral"

### 4. Build para Produção

```bash
npm install
npm run build
# Arquivo .zip será gerado em dist/
```

## Configuração de Variáveis de Ambiente

### Backend (.env)

```env
APP_NAME=Socimob
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=socimob
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

### Frontend (.env)

```env
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_TENANT_ID=1
```

### Extensão Chrome

Configurações são armazenadas em `chrome.storage.local` durante o uso.

## Testes

### Teste de Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"maria@example.com","password":"password"}'
```

### Teste de Busca de Leads

```bash
curl -X GET "http://localhost:8000/api/extension/leads/search?q=João" \
  -H "Authorization: Bearer {token}"
```

### Teste de Vinculação de Conversa

```bash
curl -X POST http://localhost:8000/api/extension/conversations/link \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "lead_id": 1,
    "contact_name": "João Silva",
    "contact_phone": "11999999999",
    "whatsapp_chat_identifier": "55119999999@c.us",
    "source": "whatsapp_web",
    "assigned_user_id": 1
  }'
```

## Troubleshooting

### Erro: "Unauthorized" (401)

- Verifique se o token está sendo enviado corretamente
- Verifique se o token não expirou
- Verifique se o middleware de autenticação está configurado

### Erro: "Forbidden" (403)

- Verifique se o usuário tem permissão para acessar o recurso
- Verifique se o tenant_id está correto
- Verifique se a policy está retornando true

### Extensão não aparece no WhatsApp Web

- Verifique se a URL é exatamente `https://web.whatsapp.com`
- Recarregue a página (F5)
- Verifique permissões em `chrome://extensions/`
- Verifique console do DevTools para erros

### Conversa não é detectada

- Abra DevTools (F12) no WhatsApp Web
- Inspecione elementos para verificar seletores CSS
- Atualize seletores em `content.js` se necessário
- Verifique console para erros de JavaScript

## Monitoramento

### Logs

Verifique logs em:
- Backend: `storage/logs/laravel.log`
- Frontend: Console do navegador (F12)
- Extensão: `chrome://extensions/` → Detalhes → Visualizar visualização de fundo

### Métricas

Monitore:
- Número de conversas criadas
- Tempo médio de resposta das APIs
- Taxa de erro de autenticação
- Uso de storage da extensão

## Manutenção

### Backup de Dados

```bash
mysqldump -u root -p socimob > backup.sql
```

### Limpeza de Sessões Expiradas

```bash
php artisan tinker

ExtensionSession::where('status', 'inactive')->delete();
```

### Atualização de Dependências

```bash
# Backend
composer update

# Frontend
npm update

# Extensão
cd chrome-extension && npm update
```

## Suporte

Para dúvidas ou problemas, consulte:
- Documentação técnica: `docs/ATENDIMENTO_360_IMPLEMENTATION.md`
- README da extensão: `chrome-extension/README.md`
- Especificação original: `docs/atendimento-360-spec.md`
