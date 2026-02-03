# Deploy Manual - Sistema de Filas e Email

## ✅ Código já está em produção
O código foi enviado com sucesso via Git.

## 🔧 Passos para completar o deploy:

### 1. Executar Migrations (via SSH ou Terminal do Hostinger)

```bash
cd domains/exclusivalarimoveis.com/public_html
php artisan migrate --force
```

Isso criará as tabelas:
- `jobs` - Armazena jobs da fila
- `failed_jobs` - Armazena jobs que falharam

### 2. Atualizar arquivo .env em produção

Adicione/modifique estas linhas no `.env`:

```bash
# Sistema de Filas
QUEUE_CONNECTION=database

# Configuração de Email
MAIL_MAILER=smtp
MAIL_DRIVER=smtp
MAIL_HOST=smtp.titan.email
MAIL_PORT=587
MAIL_USERNAME=alert@socimob.com
MAIL_PASSWORD=MundoMelhor@10
MAIL_FROM_ADDRESS=alert@socimob.com
MAIL_FROM_NAME="SOCIMOB"
MAIL_ENCRYPTION=tls
```

### 3. Iniciar Worker de Filas (Importante!)

O worker precisa estar rodando em produção para processar emails e notificações.

**Opção A - Via SSH (recomendado):**
```bash
cd domains/exclusivalarimoveis.com/public_html
nohup php artisan queue:work --tries=3 --timeout=90 --daemon > storage/logs/queue.log 2>&1 &
```

**Opção B - Via Cron Job (no painel Hostinger):**
Criar um cron job que roda a cada minuto:
```
* * * * * cd /home/u815655858/domains/exclusivalarimoveis.com/public_html && php artisan queue:work --stop-when-empty
```

### 4. Verificar se está funcionando

```bash
# Ver logs do worker
tail -f storage/logs/queue.log

# Verificar jobs na fila
php artisan queue:monitor
```

## 📋 Arquivos modificados neste deploy:

### Backend:
- ✅ `bootstrap/app.php` - Adicionado `$app->configure('mail')`
- ✅ `config/mail.php` - Suporte a MAIL_MAILER e MAIL_DRIVER
- ✅ `database/migrations/2026_02_02_175352_create_jobs_table.php`
- ✅ `database/migrations/2026_02_02_180011_create_failed_jobs_table.php`

### Migrations removidas (conflito):
- ❌ `2025_01_26_000001_add_whatsapp_template_message_to_tenants.php`
- ❌ `2026_01_27_000001_create_security_audit_logs_table.php`
- ❌ `2026_01_27_000002_create_scheduled_imports_table.php`

## 🎯 Resultado esperado:

Após completar estes passos:
- ✅ Emails serão processados em background (fila)
- ✅ Sistema não trava durante envio de emails
- ✅ Notificações WhatsApp processadas via fila
- ✅ Melhor performance geral da aplicação

## ⚠️ Importante:

O worker **DEVE** estar sempre rodando em produção. Se o servidor reiniciar, você precisa iniciar o worker novamente.

**Considere usar Supervisor** (se disponível no Hostinger) para manter o worker sempre ativo:

```ini
[program:socimob-queue]
process_name=%(program_name)s
command=php /home/u815655858/domains/exclusivalarimoveis.com/public_html/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
user=u815655858
redirect_stderr=true
stdout_logfile=/home/u815655858/domains/exclusivalarimoveis.com/public_html/storage/logs/queue.log
```
