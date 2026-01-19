# 📱 Como Limpar Cache no Celular

## ⚠️ IMPORTANTE: O código está correto, mas o navegador está com cache!

### 🔧 Opção 1: Hard Refresh (MAIS RÁPIDO)

**No Chrome/Android:**
1. Vá para: `https://lojadaesquina.store/app/login.html`
2. Toque nos **3 pontinhos** (⋮) no canto superior direito
3. Selecione **"Recarregar"** ou **"Atualizar"**
4. OU: Feche a aba e abra uma **nova aba privada/anônima**
5. Faça login novamente

### 🗑️ Opção 2: Limpar Cache Completo

**No Chrome Android:**
1. Menu (⋮) → **Configurações**
2. **Privacidade e segurança**
3. **Limpar dados de navegação**
4. Selecione:
   - ✅ **Imagens e arquivos em cache**
   - ✅ **Cookies e dados de sites**
5. Escolha **"Todo o período"**
6. Toque em **Limpar dados**

### 🧪 Opção 3: Modo Anônimo (TESTE RÁPIDO)

1. Abra uma **nova aba anônima/privada**
2. Vá para: `https://lojadaesquina.store/app/login.html`
3. Faça login como corretor
4. **Deve ir direto pro chat agora!**

### ✅ O Que Vai Acontecer

Depois de limpar o cache:
- ✅ Corretor → **Chat direto** (não passa pelo dashboard)
- ✅ Admin → Dashboard (como antes)
- ✅ Cliente → Portal imóveis

### 🔍 Debug (Opcional)

Para confirmar que está funcionando:

1. Faça login
2. **Antes de redirecionar**, abra o **DevTools**:
   - Chrome: Menu → Mais ferramentas → Ferramentas do desenvolvedor
   - Ou: Conecte o celular no PC via USB e use Chrome DevTools remoto
3. Vá na aba **Console**
4. Você deve ver:
   ```
   🔍 Login redirect - Role original: corretor
   🔍 Login redirect - Role normalizado: corretor
   ✅ Redirecionando corretor para chat
   ```

### 📞 Se Ainda Não Funcionar

Se mesmo após limpar cache ainda cair no dashboard:

1. Verifique qual **email** está usando para login
2. Confirme que o usuário tem `role = 'corretor'` no banco
3. Tire screenshot do console do navegador
4. Me envie para debug

---

**Versão do código:** v20260108-2  
**Deploy:** Janeiro 8, 2026 - 09:30
