# 📱 PWA de Chat - Guia de Uso

## 🎯 O que foi criado

Um aplicativo **Progressive Web App (PWA)** estilo WhatsApp para corretores se comunicarem com clientes através da plataforma, centralizando todo o atendimento.

## ✨ Funcionalidades

### Interface Mobile-First
- Design inspirado no WhatsApp
- Totalmente responsivo
- Otimizado para touch/gestos
- Instalável na tela inicial do celular

### Lista de Conversas
- Todas as conversas ativas do corretor
- Preview da última mensagem
- Badge com contador de mensagens não lidas
- Timestamps relativos (ex: "5m", "2h", "3d")
- Avatar com inicial do nome

### Chat Individual
- Interface de chat em tempo real
- Mensagens incoming (cliente) e outgoing (corretor)
- Status de mensagem (enviado ✓, lido ✓✓)
- Timestamps em cada mensagem
- Auto-scroll para última mensagem
- Textarea expansível (até 4 linhas)
- Enter para enviar, Shift+Enter para nova linha

### Funcionalidades PWA
- Instalável no celular (home screen)
- Service Worker para cache offline
- Notificações push (preparado)
- Funciona sem internet (conversas em cache)
- Ícone e splash screen customizados

## 🚀 Como Usar

### 1. Acessar o Chat

**Desktop/Laptop:**
```
http://127.0.0.1:8000/app/chat.html
```

**Produção:**
```
https://seu-dominio.com/app/chat.html
```

### 2. Instalar no Celular

#### Android (Chrome):
1. Abra `https://seu-dominio.com/app/chat.html`
2. Toque no menu (⋮)
3. Selecione "Adicionar à tela inicial"
4. Confirme
5. O app aparecerá como ícone na tela inicial

#### iOS (Safari):
1. Abra `https://seu-dominio.com/app/chat.html`
2. Toque no botão compartilhar (quadrado com seta)
3. Role e selecione "Adicionar à Tela de Início"
4. Nomeie o app e confirme

### 3. Usar o Chat

1. **Ver Conversas**: Ao abrir, vê lista de todas as conversas ativas
2. **Abrir Chat**: Toque em uma conversa para abrir
3. **Enviar Mensagem**: Digite e pressione Enter ou toque no ícone de enviar
4. **Voltar**: Toque na seta ← para voltar à lista
5. **Sair**: Toque no botão de logout no canto superior direito

## 🔧 Arquitetura Técnica

### Frontend
- **HTML/CSS/JavaScript**: Sem frameworks pesados
- **TailwindCSS**: Estilização via CDN
- **jQuery**: Manipulação DOM e AJAX
- **Service Worker**: Cache e funcionalidades PWA

### Backend (API)
- **Controller**: `ConversasController.php`
- **Rotas**:
  - `GET /api/admin/conversas` - Lista conversas
  - `GET /api/admin/conversas/{id}` - Detalhes da conversa
  - `GET /api/admin/conversas/{id}/mensagens` - Lista mensagens
  - `POST /api/admin/conversas/{id}/mensagens` - Envia mensagem

### Sincronização em Tempo Real
- **Polling**: Atualiza mensagens a cada 3 segundos
- **Auto-scroll**: Rola automaticamente para última mensagem
- **Marcar como lido**: Mensagens incoming marcadas ao abrir chat

### Envio via Twilio
- Mensagens são enviadas via WhatsApp Twilio automaticamente
- Status atualizado em tempo real
- Fallback em caso de erro

## 📊 Fluxo de Dados

```
[Corretor digita] 
    ↓
[POST /api/admin/conversas/{id}/mensagens]
    ↓
[Salva no banco - status: queued]
    ↓
[TwilioService envia WhatsApp]
    ↓
[Atualiza status: sent + message_sid]
    ↓
[Cliente recebe no WhatsApp]
    ↓
[Cliente responde]
    ↓
[Webhook Twilio recebe]
    ↓
[Salva como incoming]
    ↓
[Polling do corretor detecta]
    ↓
[Mensagem aparece no chat]
```

## 🎨 Personalização

### Cores
Edite em `chat.html`:
```css
/* Verde principal */
background: linear-gradient(135deg, #10B981, #059669);

/* Fundo escuro */
background: #0F172A;

/* Mensagens */
.message.incoming .message-bubble {
    background: #1E293B;
}
```

### Polling Interval
Altere em `chat.html` linha ~480:
```javascript
messagePolling = setInterval(() => {
    loadMessages();
}, 3000); // Mudar aqui (em milissegundos)
```

### Ícones e Branding
1. Crie imagens em `public/images/`:
   - `icon-192.png` (192x192px)
   - `icon-512.png` (512x512px)
2. Atualize `manifest.json`

## 🔐 Segurança

- ✅ Autenticação via Bearer Token
- ✅ Middleware `simple-auth`
- ✅ Validação de tenant_id
- ✅ Corretores veem apenas suas conversas
- ✅ Admins veem todas as conversas
- ✅ HTTPS obrigatório em produção (PWA)

## 📱 Próximos Passos (Flutter App)

Quando forem criar o app nativo Flutter, a API já está pronta:

### Endpoints Disponíveis
```dart
// Listar conversas
GET /api/admin/conversas
Headers: { Authorization: Bearer {token} }

// Mensagens
GET /api/admin/conversas/{id}/mensagens

// Enviar
POST /api/admin/conversas/{id}/mensagens
Body: { "content": "texto" }
```

### Recomendações Flutter
- Use `dio` ou `http` para requisições
- `flutter_local_notifications` para notificações
- `shared_preferences` para cache
- `websocket` ou `pusher` para real-time (upgrade do polling)
- `cached_network_image` para avatares
- `flutter_chat_ui` como base de UI

## 🐛 Troubleshooting

### Chat não carrega conversas
1. Verificar token válido: `localStorage.getItem('token')`
2. Verificar console do navegador (F12)
3. Testar API diretamente: `curl -H "Authorization: Bearer {token}" http://127.0.0.1:8000/api/admin/conversas`

### Mensagens não enviam
1. Verificar credenciais Twilio no `.env`
2. Verificar logs: `storage/logs/lumen-*.log`
3. Testar Twilio: `php teste_twilio_marcus.php`

### PWA não instala
1. HTTPS obrigatório (exceto localhost)
2. Verificar `manifest.json` válido
3. Service Worker registrado com sucesso

## 📈 Melhorias Futuras

### Curto Prazo
- [ ] WebSocket para real-time (substituir polling)
- [ ] Envio de imagens/áudio
- [ ] Emojis picker
- [ ] Indicador "digitando..."
- [ ] Notificações desktop

### Médio Prazo
- [ ] App Flutter iOS/Android
- [ ] Chatbot IA integrado
- [ ] Templates de mensagens
- [ ] Transferência de conversas entre corretores
- [ ] Relatórios de atendimento

### Longo Prazo
- [ ] Video chamadas
- [ ] Compartilhamento de localização
- [ ] Integração com CRM
- [ ] Analytics avançado

---

**Desenvolvido para SOCIMOB/Exclusiva** 🚀
