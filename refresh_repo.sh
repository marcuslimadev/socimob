#!/usr/bin/env bash
set -euo pipefail

REPO_URL="https://github.com/marcuslimadev/socimob.git"
TARGET_DIR="../socimob_fresh"

if [ -d "$TARGET_DIR/.git" ]; then
  echo "Repositorio já existe em $TARGET_DIR. Atualizando..." >&2
  git -C "$TARGET_DIR" fetch --all --prune
  git -C "$TARGET_DIR" reset --hard origin/main || git -C "$TARGET_DIR" reset --hard origin/master
else
  echo "Clonando repositório em $TARGET_DIR" >&2
  git clone "$REPO_URL" "$TARGET_DIR"
fi

echo "Repositório atualizado em $TARGET_DIR" >&2
