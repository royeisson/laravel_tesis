@echo off
echo ==========================================
echo  Iniciando Servidor de Reconocimiento Facial
echo  (Mantener esta ventana abierta)
echo ==========================================
cd /d "%~dp0"

set DB_HOST=127.0.0.1
set DB_PORT=5433
set DB_DATABASE=laravel_biometria
set DB_USERNAME=postgres
set DB_PASSWORD=postgres

python python\face_server.py 5001
pause
