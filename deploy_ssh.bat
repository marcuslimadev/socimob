@echo off
plink -P 65002 u738323131@145.223.105.168 -pw MundoMelhor@10 "cd ~/domains/lojadaesquina.store/public_html && rm -f bootstrap/cache/*.php && echo 'Cache limpo' && tail -50 storage/logs/lumen-2026-01-20.log"
pause
