#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

PYTHON=${PYTHON:-python3}

if ! command -v "$PYTHON" >/dev/null 2>&1; then
  echo "ERROR: python3 is not installed or not in PATH."
  echo "Install Python 3.11+ and try again."
  exit 1
fi

if ! "$PYTHON" -m venv .venv; then
  echo "ERROR: failed to create virtual environment."
  exit 1
fi

. .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install --no-cache-dir -r requirements.txt

cat > .env <<EOF
HF_LOCAL_MODEL=nomic-ai/nomic-embed-text-v1.5
HF_LOCAL_CACHE_DIR=${HOME}/.cache/hf_local_model
HF_LOCAL_TRUST_REMOTE_CODE=true
HF_LOCAL_MAX_TEXTS=64
HF_LOCAL_HOST=127.0.0.1
HF_LOCAL_PORT=8000
EOF

chmod +x start.sh stop.sh

echo "Local embedding service installed."
echo "Run './start.sh' to start the service."
