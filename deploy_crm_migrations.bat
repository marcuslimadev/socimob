@echo off
echo === Executando Migrations CRM ===
plink -batch u815655858@145.223.105.168 -P 65002 -pw MundoMelhor@10 "cd ~/domains/lojadaesquina.store/public_html && /opt/alt/php80/usr/bin/php artisan migrate --force"
echo.
echo === Concluido ===
pause
