# Deploy na Hostinger - Exclusiva SaaS

Este documento descreve o fluxo ideal para automatizar deploy no Hostinger usando o script `scripts/first-deploy.sh`, que agora exige o PHP 8.3 e roda Composer via linha de comando.

## 1. Visão geral do fluxo

1. **Upload ou sincronização** — coloque todo o conteúdo do repositório (sem `.github`, `vendor`, `node_modules` ou `docs`) dentro do diretório `public_html` ou outro caminho de deploy definido.
2. **Relembrar o binário do PHP** — o script depende de `/opt/alt/php83/usr/bin/php`, então confirme no painel ou via SSH que o PHP 8.3 está selecionado e que esse binário existe.
3. **Executar o script** — rode `./scripts/first-deploy.sh` na raiz do projeto; ele cuida de Composer, migrações, seeders e otimização de cache.
4. **Cache e validações** — após o script, execute `php artisan config:cache`, `php artisan route:cache`, `php artisan view:clear` para deixar o Laravel otimizado (o workflow já faz isso com o mesmo PHP 8.3).

## 2. Passo a passo manual

```bash
cd /caminho/do/projeto
chmod +x scripts/*.sh
export PHP_BIN=/opt/alt/php83/usr/bin/php
export COMPOSER_BIN=$(command -v composer)
$PHP_BIN --version
$PHP_BIN "$COMPOSER_BIN" diagnose
$PHP_BIN "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:clear

./scripts/first-deploy.sh
```

O script `first-deploy.sh` reutiliza as variáveis `PHP_BIN` e `COMPOSER_BIN`, portanto ele também respeita o PHP 8.3. Se você quiser rodar o script com Composer diferente, basta exportar `COMPOSER_BIN` antes de executar.

## 3. Configurações importantes

- **PHP 8.3:** no painel da Hostinger selecione a versão 8.3 para o site e aguarde o reinício do serviço.
- **Composer:** o comando `composer` pode estar em `/usr/bin/composer`; o script descobre esse caminho automaticamente, mas você pode fixar com `export COMPOSER_BIN=/usr/bin/composer`.
- **Extensões PHP:** ative `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `hash`, `iconv`, `intl`, `mbstring`, `pdo`, `pdo_mysql`, `openssl`, `soap`, `sockets`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip` e `redis` se usar cache em memória. O painel permite marcar as extensões e o script será executado com elas já carregadas.
- **Variáveis de ambiente:** coloque o `.env` em produção com dados reais (DB, mail, keys). Evite deixar o `.env.example` ativo; o script só copia esse arquivo na ausência do `.env`.

## 4. Automação GitHub Actions

A action `Simple Deploy` (arquivo `.github/workflows/hostinger-deploy.yml`) sincroniza os arquivos por SCP e, via SSH, exporta `PHP_BIN=/opt/alt/php83/usr/bin/php` antes de rodar `./scripts/first-deploy.sh` e os comandos `artisan config:cache`, `artisan route:cache` e `artisan view:clear`. Dessa forma o deploy usa sempre o mesmo binário testado manualmente.

## 5. Troubleshooting

- **Erro “extensão não encontrada” no Composer:** verifique `php -m` (com PHP_BIN) para confirmar se a extensão está ativa; marque-a no painel e reinicie o site.
- **Composer reclama de “stability-flags/platform”:** atualize para o Composer 2.9+ com `composer self-update --update-keys` via SSH e use esse binário em `COMPOSER_BIN`.
- **Migrações falham:** confira as credenciais de banco no `.env` e rode `php artisan migrate --force` manualmente.
- **Seeders não rodaram:** apague `.first-deploy-done` e execute `./scripts/first-deploy.sh` novamente (após confirmar os dados do banco).
- **Permissões:** se o `storage` ou `bootstrap/cache` não forem graváveis, rode `chmod -R 775 storage bootstrap/cache`.

## 6. Pós-deploy

- Depois de subir tudo e validar, confirme que o domínio está apontado para `public/` e que os certificados SSL estão ativos.
- Acesse `https://seu-domínio/app/` e entre com `admin@exclusiva.com` / `password` ou outra conta criada pelos seeders.
- Configure rota de cron no painel da Hostinger para rodar `PHP_BIN artisan schedule:run` a cada minuto.
