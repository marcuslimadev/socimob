@echo off
echo MundoMelhor@10 | plink -ssh u159964521@145.223.105.168 -P 65002 -pw "cd domains/socimob.com.br/public_html && git pull && php analyze_mapping.php"
