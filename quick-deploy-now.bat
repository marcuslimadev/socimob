@echo off
echo cd domains/lojadaesquina.store/public_html  && git pull origin master && cp -rf dist/public/* ./ | plink -ssh -P 65002 -pw "MundoMelhor@10" -batch u815655858@145.223.105.168
pause
