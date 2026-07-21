#!/usr/bin/env bash
set -e

echo "=========================================="
echo " Iniciando Servidor de Reconocimiento Facial"
echo " (Mantener esta ventana abierta)"
echo "=========================================="

cd "$(dirname "$0")"

export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=laravel
export DB_USERNAME=postgres
export DB_PASSWORD=postgres

if [ ! -d "python/.venv" ]; then
    echo "Entorno virtual no encontrado. Creando..."
    python3.11 -m venv python/.venv
fi

python/.venv/bin/python python/face_server.py 5001
