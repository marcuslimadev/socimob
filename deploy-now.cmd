@echo off
echo === LIMPANDO FRONTEND ANTIGO ===
echo cd domains/lojadaesquina.store/public_html ^&^& rm -rf dist/public ^&^& rm -f index.html ^&^& rm -rf assets ^&^& ls -la | plink -ssh -P 65002 -pw "MundoMelhor@10" -batch u815655858@145.223.105.168

echo.
echo === FAZENDO GIT PULL ===
echo cd domains/lojadaesquina.store/public_html ^&^& git pull origin master | plink -ssh -P 65002 -pw "MundoMelhor@10" -batch u815655858@145.223.105.168

echo.
echo === COPIANDO NOVO BUILD ===
echo cd domains/lojadaesquina.store/public_html ^&^& cp -rf dist/public/* ./ ^&^& ls -la index.html assets/ ^| head -10 | plink -ssh -P 65002 -pw "MundoMelhor@10" -batch u815655858@145.223.105.168

pause
