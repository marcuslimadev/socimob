#!/usr/bin/env bash
set -euo pipefail

# Procura marcadores de conflito comuns em toda a base (excluindo vendor e node_modules)
if rg --hidden --no-messages \
      --glob '!vendor/**' --glob '!node_modules/**' --glob '!.git/**' \
      '^(<<<<<<<|=======|>>>>>>>)' >/tmp/merge_conflicts_found.txt; then
  echo "⚠️ Foram encontrados potenciais conflitos de merge:"
  cat /tmp/merge_conflicts_found.txt
  exit 1
fi

echo "✅ Nenhum marcador de conflito encontrado."
