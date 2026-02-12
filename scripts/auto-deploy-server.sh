#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${1:-$PWD}"
BRANCH="${2:-master}"
PHP_BIN="${PHP_BIN:-/opt/alt/php83/usr/bin/php}"

cd "$APP_DIR"

echo "[deploy] app_dir=$APP_DIR branch=$BRANCH"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "[deploy] ERROR: diretório não é repositório git"
  exit 1
fi

echo "[deploy] atualizando código"
git fetch --all --prune

if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
  git checkout "$BRANCH" 2>/dev/null || git checkout -b "$BRANCH" "origin/$BRANCH"
  git pull --ff-only origin "$BRANCH"
else
  echo "[deploy] branch origin/$BRANCH não existe, mantendo branch atual"
  git pull --ff-only
fi

if [ -f composer.json ]; then
  echo "[deploy] composer install"
  if command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
  elif [ -f composer.phar ]; then
    "$PHP_BIN" composer.phar install --no-interaction --prefer-dist --optimize-autoloader --no-dev
  else
    echo "[deploy] WARN: composer não encontrado, pulando instalação de dependências PHP"
  fi
fi

if [ -f package.json ] && [ -d client ]; then
  if command -v pnpm >/dev/null 2>&1; then
    echo "[deploy] build frontend com pnpm"
    pnpm install --frozen-lockfile
    pnpm run build
  else
    echo "[deploy] WARN: pnpm não encontrado, usando artefato já versionado em dist/public"
  fi
fi

if [ -f dist/public/index.html ] && [ -d dist/public/assets ]; then
  echo "[deploy] vinculando index/assets para servir build sem cópia"
  ln -sfn dist/public/index.html index.html
  ln -sfn dist/public/assets assets
else
  echo "[deploy] WARN: dist/public ausente, mantendo arquivos atuais"
fi

if [ -x "$PHP_BIN" ] && [ -f artisan ]; then
  echo "[deploy] verificando migrations pendentes"
  PENDING_MIGRATIONS=$($PHP_BIN artisan migrate:status --no-ansi 2>/dev/null | grep -ci "Pending" || true)
  if [ "$PENDING_MIGRATIONS" -gt 0 ]; then
    echo "[deploy] executando migrations pendentes"
    $PHP_BIN artisan migrate --force
  else
    echo "[deploy] sem migrations pendentes"
  fi

  echo "[deploy] limpando/cacheando config"
  $PHP_BIN artisan optimize:clear || true
  $PHP_BIN artisan config:cache || true
fi

echo "[deploy] concluído em $(date)"