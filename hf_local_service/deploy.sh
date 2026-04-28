#!/usr/bin/env bash
# Deploy script para o serviço de embeddings local no servidor remoto
# Execução: bash deploy.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="hf-local-embeddings"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
PYTHON_BIN="/usr/bin/python3"
VENV_PATH="${SCRIPT_DIR}/.venv"
USE_SYSTEMD=0

echo "=== Instalando serviço de embeddings local ==="

# Validar Python
if ! command -v "$PYTHON_BIN" &> /dev/null; then
  echo "ERROR: Python 3 não encontrado em $PYTHON_BIN"
  exit 1
fi

echo "Python encontrado: $($PYTHON_BIN --version)"

# Criar virtualenv
echo "Criando ambiente virtual..."
$PYTHON_BIN -m venv "$VENV_PATH"

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
EOF
fi

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
