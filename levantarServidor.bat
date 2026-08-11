@echo off
title Usuarios

cd /d "C:\Users\Carlos\Documents\proyectos\scripPhp"

echo ========================================
echo      Servidor PHP local
echo ========================================
echo.
echo Carpeta: %CD%
echo.
echo Servidor disponible en:
echo http://localhost:8000
echo.
echo Presiona Ctrl+C para detenerlo.
echo ========================================
echo.

php -S localhost:8000
pause