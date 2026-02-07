$serverUser = "u815655858"
$serverHost = "145.223.105.168"
$serverPort = "65002"
$serverPass = "MundoMelhor@10"
$serverPath = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== ATUALIZANDO BANCO DE DADOS ===" -ForegroundColor Cyan
Write-Host ""

# Ler credenciais do .env do servidor
$commands = @"
cd $serverPath && \
DB_HOST=\$(grep DB_HOST .env | cut -d '=' -f2) && \
DB_DATABASE=\$(grep DB_DATABASE .env | cut -d '=' -f2) && \
DB_USERNAME=\$(grep DB_USERNAME .env | cut -d '=' -f2) && \
DB_PASSWORD=\$(grep DB_PASSWORD .env | cut -d '=' -f2) && \
echo 'Atualizando mensagem 417...' && \
mysql -h \$DB_HOST -u \$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE -e \"UPDATE mensagens SET media_url = '/storage/leads/172/media/lead_172_msg_417_1770504818.jpg' WHERE id = 417;\" && \
echo '✅ Mensagem atualizada!' && \
mysql -h \$DB_HOST -u \$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE -e \"SELECT id, media_url FROM mensagens WHERE id = 417;\"
"@

Write-Host "Conectando ao servidor..." -ForegroundColor Gray
echo "exit" | plink -P $serverPort -pw $serverPass -batch $serverUser@$serverHost $commands

Write-Host ""
Write-Host "✅ BANCO ATUALIZADO!" -ForegroundColor Green
Write-Host "Recarregue o chat: https://lojadaesquina.store/chat?leadId=172" -ForegroundColor Cyan
