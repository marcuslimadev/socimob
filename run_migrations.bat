@echo off
echo === Executando migrations e setup de storage ===
plink -batch u815655858@145.223.105.168 -P 65002 -pw MundoMelhor@10 "cd ~/domains/lojadaesquina.store/public_html && php artisan migrate --force && ln -sf ../storage/app/public public/storage 2>/dev/null || true && ls -la public/storage"
echo.
echo === Concluido ===
