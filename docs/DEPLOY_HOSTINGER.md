# Deploy na Hostinger - Exclusiva SaaS

Este guia mostra como fazer deploy do sistema na Hostinger usando **scripts manuais** de forma simples e direta.

## 🚀 Deploy em 3 Passos

### 1️⃣ Upload dos Arquivos
- **Via FTP/SFTP**: Upload de toda a pasta do projeto
- **Via Git**: `git clone` direto no servidor
- **Localização**: `public_html/` ou subpasta como `public_html/exclusiva/`

### 2️⃣ Executar Setup
```bash
# No servidor via SSH
cd /caminho/do/projeto
chmod +x scripts/*.sh
./scripts/first-deploy.sh
```

### 3️⃣ Acessar Sistema
- **URL**: `https://seu-dominio.com/app/`
- **Login**: `contato@exclusiva.com.br` / `Teste@123`

## ⚙️ Configuração Prévia

### Hostinger - Requisitos:
- ✅ **PHP 8.1+** ativo
- ✅ **MySQL** configurado  
- ✅ **SSH** habilitado
- ✅ **Composer** disponível

### Arquivo .env (criar no servidor):
```env
APP_ENV=production
DB_HOST=localhost
DB_DATABASE=exclusiva
DB_USERNAME=seu_user_mysql
DB_PASSWORD=sua_senha_mysql

# Outras configurações conforme necessário
MAIL_DRIVER=smtp
```

## 🌱 O que é Criado Automaticamente

O script **`first-deploy.sh`** cria:

### 🏢 **Imobiliária Exclusiva**
- Tenant configurado com plano Premium
- API Token gerado
- Configurações básicas

### 👥 **Usuários Prontos**
| Email | Senha | Perfil |
|-------|--------|--------|
| admin@exclusiva.com | `password` | Super Admin |
| contato@exclusiva.com.br | `Teste@123` | Admin |
| alexsandra@exclusiva.com.br | `Senha@123` | Admin |
| marcus@exclusiva.com.br | `Dev@123` | Admin |
| corretor@exclusiva.com.br | `Corretor@123` | Corretor |

## 🔄 Deploy Subsequente

Para atualizações futuras:
1. **Upload dos novos arquivos** (substitui existentes)
2. **Executar script novamente**: `./scripts/first-deploy.sh`
3. **Seeders não são executados** (dados preservados)

## 🔧 Troubleshooting

### ❌ **Script não executa**
```bash
chmod +x scripts/*.sh
```

### ❌ **Erro de banco**  
1. Verificar credenciais no `.env`
2. Confirmar que banco `exclusiva` existe
3. Testar: `mysql -u user -p exclusiva`

### ❌ **Erro de permissões**
```bash
chmod -R 775 storage bootstrap/cache
```

### ❌ **Verificar se deu certo**
```bash
./scripts/verify-deploy.sh
```

## 📋 Dicas Extras

### 🌐 **Configurar Domínio**
1. Apontar DNS para Hostinger  
2. Configurar SSL no painel
3. Ajustar domain do tenant (se necessário)

### 🔄 **Recriar Dados (se necessário)**  
```bash
rm .first-deploy-done
./scripts/first-deploy.sh
```

### 📞 **Logs de Erro**
```bash  
tail -f storage/logs/lumen-*.log
```

---

✅ **Sistema pronto!** Acesse `https://seu-dominio.com/app/` e faça login com as credenciais criadas.
   - `php artisan migrate --force`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:clear`

## 3. Dados Iniciais (Primeiro Deploy)

No **primeiro deploy**, o sistema automaticamente executará os seeders que criam:

### 🏢 Tenant Exclusiva
- **Nome**: Exclusiva Imóveis  
- **Domain**: exclusiva.localhost (ajustar conforme necessário)
- **Plano**: Premium ativo por 1 ano
- **API Token**: Gerado automaticamente

### 👥 Usuários Criados
| Nome | Email | Senha | Role | 
|------|--------|-------|------|
| Super Administrador | admin@exclusiva.com | `password` | super_admin |
| Contato Exclusiva | contato@exclusiva.com.br | `Teste@123` | admin |
| Alexsandra Silva | alexsandra@exclusiva.com.br | `Senha@123` | admin |
| Marcus Lima | marcus@exclusiva.com.br | `Dev@123` | admin |
| Corretor Demo | corretor@exclusiva.com.br | `Corretor@123` | agent |

### 🔄 Deploys Subsequentes
- Os seeders **não são executados** novamente
- Sistema detecta através do arquivo `.first-deploy-done`
- Apenas migrações e atualizações de código são aplicadas

### 📝 Scripts Alternativos
Se preferir executar manualmente:
```bash
# Linux/Mac
./scripts/first-deploy.sh

# Windows  
scripts\first-deploy.bat

# Ou apenas os seeders
php database/seeders/DatabaseSeeder.php
```

## 4. Webhooks

Atualize os endpoints externos para apontarem para seu domínio Hostinger:
- `https://seu-dominio/github/webhook` (GitHub)
- `https://seu-dominio/webhook/whatsapp` (Twilio/Evolution)
- `https://seu-dominio/api/webhooks/pagar-me` (Pagar.me)

## 4. Monitoramento

- Garanta que `storage/logs` e `bootstrap/cache` estejam graváveis no Hostinger; ative rotação de logs se disponível.
- Use o painel de cron da Hostinger para rodar `php artisan schedule:run` a cada minuto.
