# 🧪 Teste Rápido do Sistema

## Passo a Passo para Testar

### 1. Verificar MySQL
```powershell
Get-Service mysql*
# Status deve ser "Running"
```

Se não estiver rodando:
```powershell
Start-Service mysql
```

### 2. Verificar Banco de Dados
```powershell
mysql -u root -e "SHOW DATABASES LIKE 'exclusiva';"
```

Se não existir:
```powershell
mysql -u root -e "CREATE DATABASE exclusiva;"
```

### 3. Iniciar Servidor
**Opção A (recomendada):**
```bash
# Duplo clique em:
backend\START.bat
```

**Opção B (manual):**
```powershell
cd C:\Projetos\saas\backend
php -S 127.0.0.1:8000 -t public
```

### 4. Testar API
Abra um novo terminal e execute:

```powershell
# Teste 1: API está online?
Invoke-WebRequest -Uri "http://127.0.0.1:8000" -UseBasicParsing

# Deve retornar:
# StatusCode: 200
# Content: {"app":"Exclusiva Lar CRM","version":"Lumen (10.0.4)","status":"online"}

# Teste 2: Frontend está acessível?
Invoke-WebRequest -Uri "http://127.0.0.1:8000/app/login.html" -UseBasicParsing

# Deve retornar:
# StatusCode: 200
# Content: (HTML da página)
```

### 5. Testar Login (API)
```powershell
$body = @{
    email = "admin@exclusiva.com"
    senha = "password"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body $body

$response
# Deve retornar:
# success : True
# token   : eyJ1c2VyX2lkIjoxfQ==...
# user    : @{id=1; name=Administrator; email=admin@exclusiva.com; ...}
```

### 6. Testar no Navegador
1. Abra: `http://127.0.0.1:8000/app/`
2. Deve redirecionar para `/app/login.html`
3. Login já vem pré-preenchido: `admin@exclusiva.com` / `password`
4. Clique em "Entrar"
5. Deve ir para `/app/dashboard.html`
6. Verifique no canto superior direito: deve mostrar "Administrator" e "SUPER_ADMIN"

### 7. Testar Páginas
Navegue pelos menus:
- ✅ **Leads**: Deve mostrar tabela (pode estar vazia ou com dados de exemplo)
- ✅ **Imóveis**: Deve mostrar grid de cards (com dados de exemplo)
- ✅ **Conversas**: Deve mostrar layout tipo WhatsApp
- ✅ **Configurações**: Deve mostrar 4 abas

### 8. Verificar Console do Navegador
Pressione F12 → Console

Deve ver logs como:
```
✓ Login page carregada
✓ Token encontrado, redirecionando...
✓ Dashboard carregado
✓ Usuário: {id: 1, name: "Administrator", ...}
```

## ❌ Problemas Comuns

### Erro: "Cannot connect to database"
```powershell
# Verificar MySQL
Get-Service mysql*

# Criar banco se necessário
mysql -u root -e "CREATE DATABASE exclusiva;"

# Verificar .env
Get-Content backend\.env | Select-String "DB_"
```

### Erro: "Address already in use"
```powershell
# Parar processos PHP
Get-Process php | Stop-Process -Force

# Reiniciar
cd backend
php -S 127.0.0.1:8000 -t public
```

### Erro: "404 Not Found" ao acessar /app/
```powershell
# Verificar arquivos HTML
Get-ChildItem backend\public\app\

# Deve listar 7 arquivos:
# - index.html
# - login.html
# - dashboard.html
# - leads.html
# - imoveis.html
# - conversas.html
# - configuracoes.html
```

### Página em branco após login
1. Abra DevTools (F12)
2. Vá para Console
3. Procure por erros em vermelho
4. Verifique se jQuery e TailwindCSS carregaram (aba Network)

### Token inválido / Não autenticado
```javascript
// No console do navegador (F12 → Console)
localStorage.clear()
// Depois recarregue a página
```

## ✅ Checklist de Teste Completo

- [ ] MySQL está rodando
- [ ] Banco `exclusiva` existe
- [ ] Servidor PHP iniciado (porta 8000)
- [ ] API responde em `http://127.0.0.1:8000`
- [ ] Frontend carrega em `http://127.0.0.1:8000/app/`
- [ ] Login funciona (credenciais: admin@exclusiva.com / password)
- [ ] Dashboard aparece após login
- [ ] Todas as 6 páginas são acessíveis
- [ ] Logout funciona e volta para login
- [ ] Console não mostra erros

## 📊 Resultados Esperados

### ✅ Sucesso Total
```
🟢 MySQL: Running
🟢 Database: exclusiva exists
🟢 PHP Server: Listening on 127.0.0.1:8000
🟢 API: Status 200
🟢 Frontend: Status 200
🟢 Login: Token received
🟢 Dashboard: Loaded
🟢 All pages: Accessible
✨ SISTEMA FUNCIONANDO PERFEITAMENTE!
```

### ⚠️ Sucesso Parcial
- Sistema carrega mas alguns dados não aparecem
- Pode ser que a API ainda não tenha todos os endpoints implementados
- Frontend está OK, backend precisa de mais trabalho

### ❌ Falha
- Servidor não inicia: verificar PHP e MySQL
- Páginas não carregam: verificar se os arquivos HTML estão em `backend/public/app/`
- Login não funciona: verificar banco de dados e usuários

## 🎯 Próximo Passo Após Testar

Se tudo funcionar:
1. ✅ Marque este teste como concluído
2. 📝 Documente quaisquer problemas encontrados
3. 🚀 Comece a usar o sistema!

Se houver problemas:
1. 🔍 Verifique os logs em `backend/storage/logs/`
2. 🐛 Use o DevTools do navegador para debug
3. 📖 Consulte `SERVIDOR_UNICO.md` para mais ajuda

---

**Teste criado em:** Dezembro 2024
**Versão do sistema:** 2.0 - Servidor Único PHP
