#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"
if [ ! -x "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi

if [ -z "$PHP_BIN" ]; then
  echo "PHP não encontrado (ajuste a variável PHP_BIN)." >&2
  exit 1
fi

COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
if [ -z "$COMPOSER_BIN" ]; then
  echo "Composer não encontrado (ajuste a variável COMPOSER_BIN)." >&2
  exit 1
fi

if [[ "$COMPOSER_BIN" == *.phar ]]; then
  COMPOSER_CMD=("$PHP_BIN" "$COMPOSER_BIN")
else
  COMPOSER_CMD=("$COMPOSER_BIN")
fi

cd "$ROOT_DIR"

echo "Usando PHP: $PHP_BIN"
echo "Usando Composer: $COMPOSER_BIN"

if [ ! -d storage ] || [ ! -d bootstrap/cache ]; then
  echo "Preparando diretórios de cache..."
  mkdir -p storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
fi

if [ ! -f .env ] && [ -f .env.example ]; then
  echo "Criando .env a partir de .env.example..."
  cp .env.example .env
fi

set -x
"${COMPOSER_CMD[@]}" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$PHP_BIN" artisan key:generate --force
"$PHP_BIN" artisan migrate --force

if [ ! -f .first-deploy-done ]; then
  "$PHP_BIN" artisan db:seed --force
  touch .first-deploy-done
fi

"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan optimize
set +x

echo "Deploy inicial concluído com sucesso."
