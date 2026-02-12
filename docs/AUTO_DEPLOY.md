# Auto Deploy (Push -> Produção)

Fluxo configurado:

1. Você faz `git push` para `main` ou `master`.
2. O workflow `.github/workflows/hostinger-deploy.yml` executa no GitHub.
3. O servidor roda `scripts/auto-deploy-server.sh`, que:
- faz `git pull`
- instala dependências PHP (quando necessário)
- builda frontend com `pnpm` (se disponível)
- publica frontend via symlink (`index.html` e `assets` -> `dist/public`)
- executa migrate apenas se houver pendências

## Secrets necessários no GitHub

Configure no repositório (Settings -> Secrets and variables -> Actions):

- `HOSTINGER_SSH_HOST`
- `HOSTINGER_SSH_PORT`
- `HOSTINGER_SSH_USERNAME`
- `HOSTINGER_SSH_PASSWORD` **ou** `HOSTINGER_SSH_KEY`
- `HOSTINGER_SSH_KEY_PASSPHRASE` (se usar chave com passphrase)
- `HOSTINGER_DEPLOY_PATH` (ex: `~/domains/seudominio/public_html`)

## Pré-requisitos no servidor

- Repositório clonado em `HOSTINGER_DEPLOY_PATH`
- Acesso do servidor ao repositório remoto (`origin`)
- PHP 8.3 em `/opt/alt/php83/usr/bin/php` (ou ajuste `PHP_BIN`)
- `pnpm` instalado (recomendado; sem isso usa `dist/public` versionado)

