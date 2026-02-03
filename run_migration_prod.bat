@echo off
echo === Executando migrations no servidor ===
plink -batch -ssh u815655858@145.223.105.168 -P 65002 -pw Mm17@27ma06 "cd ~/domains/lojadaesquina.store/public_html && php artisan migrate --force"
echo.
echo === Migration concluida ===
pause
