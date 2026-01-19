# 🎉 SOCIMOB - Sistema Integrado (Servidor Único PHP)

## 🚀 Início Rápido

### 1. Iniciar o Servidor

**Windows:**
```bash
# Clique duplo no arquivo ou execute:
START.bat
```

**Ou execute manualmente:**
```bash
cd backend
php -S 127.0.0.1:8000 -t public
```

### 2. Acessar o Sistema

Abra seu navegador e acesse:
```
http://127.0.0.1:8000/app/
```

### 3. Login

- **Email:** `admin@exclusiva.com`
- **Senha:** `password`

## 📁 Estrutura Consolidada

```
backend/
├── public/
│   ├── index.php          # API Backend (Lumen)
│   └── app/               # Frontend HTML/jQuery
│       ├── index.html     # Redirecionamento automático
│       ├── login.html     # Página de login
│       ├── dashboard.html # Dashboard principal
│       ├── leads.html     # Gestão de leads
│       ├── imoveis.html   # Gestão de imóveis
│       ├── conversas.html # Sistema de mensagens
│       └── configuracoes.html # Configurações
├── app/                   # Código do backend
├── routes/                # Rotas da API
└── START.bat             # Script de inicialização

frontend/                 # ❌ NÃO É MAIS NECESSÁRIO
```

## ✨ Vantagens do Servidor Único

✅ **Um único comando** para iniciar tudo
✅ **Uma única porta** (8000) - sem complexidade
✅ **Sem Node.js/npm** - apenas PHP
✅ **Sem Vite/Vue** - HTML/jQuery simples
✅ **Sem proxy/CORS** - tudo no mesmo domínio
✅ **Deploy mais simples** - copie a pasta `backend` e pronto

## 🔧 Tecnologias

### Backend (API)
- **Lumen 10** (Laravel micro-framework)
- **PHP 8.1+**
- **MySQL** (banco exclusiva)
- **Autenticação:** Bearer Token

### Frontend (UI)
- **HTML5** puro
- **jQuery 3.7.1** (via CDN)
- **TailwindCSS** (via CDN)
- **JavaScript** vanilla

## 📝 API Endpoints

Todos os endpoints estão disponíveis em `/api/`:

### Autenticação
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Dados do usuário

### Recursos
- `GET /api/leads` - Listar leads
- `POST /api/leads` - Criar lead
- `GET /api/properties` - Listar imóveis
- `POST /api/properties` - Criar imóvel
- `GET /api/conversas` - Listar conversas
- `GET /api/conversas/{id}/mensagens` - Mensagens

## 🎨 Páginas

### 1. Login (`/app/login.html`)
- Formulário com credenciais pré-preenchidas
- Validação e mensagens de erro
- Redireciona para dashboard após login

### 2. Dashboard (`/app/dashboard.html`)
- Cards com contadores (Leads, Imóveis, Conversas)
- Menu de navegação para todas as seções
- Link especial para super_admin

### 3. Leads (`/app/leads.html`)
- Tabela com todos os leads
- Filtros por nome/email e status
- Modal para criar novo lead
- Ações: editar e excluir

### 4. Imóveis (`/app/imoveis.html`)
- Grid de cards com imóveis
- Filtros por tipo, status e finalidade
- Modal completo para cadastro
- Dados: título, tipo, preço, área, quartos, banheiros

### 5. Conversas (`/app/conversas.html`)
- Interface estilo WhatsApp
- Lista de conversas à esquerda
- Chat completo à direita
- Envio de mensagens em tempo real

### 6. Configurações (`/app/configuracoes.html`)
- **Aba Perfil:** dados pessoais e CRECI
- **Aba Empresa:** dados da imobiliária
- **Aba Integrações:** WhatsApp, Email, Portais
- **Aba Segurança:** alteração de senha

## 🔐 Autenticação

O sistema usa **localStorage** para manter a sessão:

```javascript
// Salvo no login
localStorage.setItem('token', response.token);
localStorage.setItem('user', JSON.stringify(response.user));

// Verificado em todas as páginas
const token = localStorage.getItem('token');
if (!token) {
    window.location.href = 'login.html';
}
```

## 🐛 Troubleshooting

### Erro: "Não foi possível conectar"
1. Verifique se o servidor PHP está rodando (START.bat)
2. Confirme que o MySQL está ativo
3. Verifique o banco `exclusiva` existe

### Erro: "Credenciais inválidas"
1. Use `admin@exclusiva.com` / `password`
2. Verifique os usuários no banco:
   ```sql
   SELECT * FROM users;
   ```

### Página em branco
1. Abra o DevTools (F12) → Console
2. Verifique erros de JavaScript
3. Confirme que jQuery e TailwindCSS carregaram (aba Network)

### API não responde
1. Acesse `http://127.0.0.1:8000` diretamente
2. Deve retornar JSON com informações do app
3. Verifique logs em `backend/storage/logs/`

## 📦 Deploy (Produção)

### Opção 1: Servidor Compartilhado
1. Copie a pasta `backend/` inteira
2. Configure `.env` com dados do servidor
3. Execute `composer install`
4. Aponte o domínio para `public/`

### Opção 2: VPS/AWS
1. Use o `docker-compose.yml` em `docker/`
2. Ou configure Nginx + PHP-FPM manualmente
3. Veja `docker/GUIA_DOCKER_AWS.md` para detalhes

## 🎯 Próximos Passos

- ✅ Sistema funcionando com servidor único
- ⏳ Implementar endpoints reais da API
- ⏳ Adicionar upload de imagens
- ⏳ Integração com WhatsApp
- ⏳ Sistema de notificações
- ⏳ Relatórios e dashboard analytics

## 📞 Suporte

- Documentação completa em `docs/`
- Arquivo de instruções AI em `.github/copilot-instructions.md`
- Scripts de teste em `backend/*.php`

---

**Desenvolvido com ❤️ para SOCIMOB - Sistema de Gestão Imobiliária**
