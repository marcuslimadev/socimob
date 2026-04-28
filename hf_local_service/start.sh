#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

if [ ! -d .venv ]; then
  echo "ERROR: virtual environment not found. Run ./install.sh first."
  exit 1
fi

source .venv/bin/activate

if [ -f .env ]; then
  set -a
  . ./.env
  set +a
fi

LOG_FILE="hf_local_service.log"
PID_FILE="hf_local_service.pid"
HOST="${HF_LOCAL_HOST:-127.0.0.1}"
PORT="${HF_LOCAL_PORT:-8000}"

if [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" >/dev/null 2>&1; then
  echo "Service already running with PID $(cat "$PID_FILE")."
  exit 0
fi

nohup uvicorn app:app --host "$HOST" --port "$PORT" --workers 1 > "$LOG_FILE" 2>&1 &
PID=$!
echo "$PID" > "$PID_FILE"

echo "Local embedding service started."
echo "PID: $PID"
echo "URL: http://$HOST:$PORT"
echo "Logs: $ROOT_DIR/$LOG_FILE"
