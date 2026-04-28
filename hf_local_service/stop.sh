#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT_DIR"

PID_FILE="hf_local_service.pid"
if [ ! -f "$PID_FILE" ]; then
  echo "No PID file found. Service may not be running."
  exit 0
fi

PID=$(cat "$PID_FILE")
if kill -0 "$PID" >/dev/null 2>&1; then
  kill "$PID"
  echo "Stopped service PID $PID."
else
  echo "Process $PID is not running."
fi
rm -f "$PID_FILE"