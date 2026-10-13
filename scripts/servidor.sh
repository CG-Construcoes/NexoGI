#!/usr/bin/env bash
set -euo pipefail

cd /home/suporte/Documents/NexoGI

PORT=${APP_PORT:-8000}
LOG=storage/server.log
mkdir -p storage

# Mata servidor anterior na mesma porta, se houver
fuser -k ${PORT}/tcp 2>/dev/null || true

nohup php -S 0.0.0.0:${PORT} -t public/ > "$LOG" 2>&1 &
echo $! > storage/server.pid

echo "Servidor iniciado em http://0.0.0.0:${PORT} (PID $(cat storage/server.pid))"
echo "Log: $LOG"
