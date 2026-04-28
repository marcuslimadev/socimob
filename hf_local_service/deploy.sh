#!/usr/bin/env bash
# Deploy script para o serviço de embeddings local no servidor remoto
# Execução: bash deploy.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="hf-local-embeddings"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
PYTHON_BIN="${PYTHON_BIN:-}"
VENV_PATH="${SCRIPT_DIR}/.venv"
USE_SYSTEMD=0

echo "=== Instalando serviço de embeddings local ==="

# Validar Python. Em hospedagens compartilhadas, /usr/bin/python3 pode ser antigo.
if [ -z "$PYTHON_BIN" ]; then
  for candidate in \
    /opt/alt/python312/bin/python3 \
    /opt/alt/python311/bin/python3 \
    /opt/alt/python310/bin/python3 \
    /opt/alt/python39/bin/python3 \
    python3.12 python3.11 python3.10 python3.9 python3
  do
    if command -v "$candidate" >/dev/null 2>&1 || [ -x "$candidate" ]; then
      PYTHON_BIN="$candidate"
      break
    fi
  done
fi

if [ -z "$PYTHON_BIN" ] || ! "$PYTHON_BIN" --version >/dev/null 2>&1; then
  echo "ERROR: Python 3.9+ não encontrado."
  exit 1
fi

echo "Python encontrado: $($PYTHON_BIN --version)"

PYTHON_VERSION="$("$PYTHON_BIN" - <<'PY'
import sys
print(f"{sys.version_info.major}.{sys.version_info.minor}")
PY
)"

case "$PYTHON_VERSION" in
  3.9|3.10|3.11|3.12|3.13) ;;
  *)
    echo "ERROR: Python $PYTHON_VERSION encontrado, mas o serviço exige Python 3.9+."
    exit 1
    ;;
esac

# Criar virtualenv
echo "Criando ambiente virtual..."
if [ -d "$VENV_PATH" ] && [ ! -x "$VENV_PATH/bin/python" ]; then
  rm -rf "$VENV_PATH"
fi
"$PYTHON_BIN" -m venv "$VENV_PATH"

# Ativar e instalar dependências
echo "Instalando dependências..."
source "$VENV_PATH/bin/activate"
pip install --upgrade pip
pip install --no-cache-dir -r "$SCRIPT_DIR/requirements.txt"

if [ ! -f "$SCRIPT_DIR/.env" ]; then
  cat > "$SCRIPT_DIR/.env" <<EOF
HF_LOCAL_MODEL=nomic-ai/nomic-embed-text-v1.5
HF_LOCAL_CACHE_DIR=${HOME}/.cache/hf_local_model
HF_LOCAL_TRUST_REMOTE_CODE=true
HF_LOCAL_MAX_TEXTS=64
HF_LOCAL_HOST=127.0.0.1
HF_LOCAL_PORT=8000
HF_HUB_DISABLE_XET=1
HF_HUB_DISABLE_PROGRESS_BARS=1
TOKENIZERS_PARALLELISM=false
OMP_NUM_THREADS=1
MKL_NUM_THREADS=1
NUMEXPR_NUM_THREADS=1
EOF
fi

ensure_env() {
  local key="$1"
  local value="$2"

  if grep -q "^${key}=" "$SCRIPT_DIR/.env"; then
    return
  fi

  printf "%s=%s\n" "$key" "$value" >> "$SCRIPT_DIR/.env"
}

ensure_env HF_HUB_DISABLE_XET 1
ensure_env HF_HUB_DISABLE_PROGRESS_BARS 1
ensure_env TOKENIZERS_PARALLELISM false
ensure_env OMP_NUM_THREADS 1
ensure_env MKL_NUM_THREADS 1
ensure_env NUMEXPR_NUM_THREADS 1

if command -v systemctl >/dev/null 2>&1 && command -v sudo >/dev/null 2>&1 && sudo -n true >/dev/null 2>&1; then
  USE_SYSTEMD=1
fi

if [ "$USE_SYSTEMD" -eq 1 ]; then
  # Criar systemd service
  echo "Registrando serviço systemd..."
  sudo tee "$SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=Local Nomic Embeddings Service
After=network.target

[Service]
Type=simple
User=$(whoami)
WorkingDirectory=$SCRIPT_DIR
EnvironmentFile=$SCRIPT_DIR/.env
ExecStart=$VENV_PATH/bin/python -m uvicorn app:app --host 127.0.0.1 --port 8000
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

  # Recarregar systemd e iniciar
  echo "Habilitando e iniciando o serviço..."
  sudo systemctl daemon-reload
  sudo systemctl enable "$SERVICE_NAME"
  sudo systemctl restart "$SERVICE_NAME"
else
  echo "systemd indisponível; iniciando serviço com nohup."
  bash "$SCRIPT_DIR/stop.sh" || true
  bash "$SCRIPT_DIR/start.sh"
fi

echo "✓ Serviço instalado e iniciado!"
echo ""
if [ "$USE_SYSTEMD" -eq 1 ]; then
  echo "Status do serviço:"
  sudo systemctl status "$SERVICE_NAME"
else
  echo "Status do serviço:"
  if [ -f "$SCRIPT_DIR/hf_local_service.pid" ]; then
    PID="$(cat "$SCRIPT_DIR/hf_local_service.pid")"
    ps -p "$PID" -o pid=,stat=,cmd=
  fi
fi
echo ""
echo "Verificar logs:"
if [ "$USE_SYSTEMD" -eq 1 ]; then
  echo "  sudo journalctl -u $SERVICE_NAME -f"
else
  echo "  tail -f $SCRIPT_DIR/hf_local_service.log"
fi
