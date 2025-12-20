# Deploy automático na Hostinger

Este projeto agora roda exclusivamente na Hostinger em produção. Abaixo está o fluxo recomendado para manter o backend atualizado via GitHub Actions.

## 1. Ambiente Hostinger

1. Garanta um plano Hostinger com PHP 8.1+, MySQL, SSH e Composer.
2. Anote os dados SSH (host, usuário, porta, senha ou chave) e o caminho onde o backend será publicado (ex.: `/home/u12345678/public_html/exclusiva`).
3. Prepare o `.env` no servidor com todos os valores sensíveis: banco, e-mail, webhooks, `GITHUB_WEBHOOK_SECRET` etc.

## 2. GitHub Actions

1. No repositório GitHub, cadastre os seguintes segredos:
   - `HOSTINGER_SSH_HOST`
   - `HOSTINGER_SSH_USERNAME`
   - `HOSTINGER_SSH_PORT` (opcional, padrão `22`)
   - `HOSTINGER_SSH_PASSWORD` **ou** `HOSTINGER_SSH_KEY` (com `HOSTINGER_SSH_KEY_PASSPHRASE`, se necessário)
   - `HOSTINGER_DEPLOY_PATH`
   - `HOSTINGER_ASSET_PATH` (opcional; o local onde as assets públicas devem cair, se for diferente de `HOSTINGER_DEPLOY_PATH`)
2. A workflow `.github/workflows/hostinger-deploy.yml` dispara em push para `main`/`master`, copia o conteúdo de `backend/` para o servidor e executa:
   - `composer install --no-dev --prefer-dist`
   - `php artisan migrate --force`
   - `php artisan config:cache`
   - `php artisan route:cache`
   - `php artisan view:clear`

## 3. Webhooks

Atualize os endpoints externos para apontarem para a instância Hostinger:
- `https://seu-dominio/github/webhook` (GitHub)
- `https://seu-dominio/webhook/whatsapp` (Twilio/Evolution)
- `https://seu-dominio/api/webhooks/pagar-me` (Pagar.me)

## 4. Monitoramento

- Configure o painel Hostinger para manter logs rotacionados (`storage/logs` tem que ser gravável).
- Use o painel de tarefas cron da Hostinger para rodar `php artisan schedule:run` a cada minuto.
