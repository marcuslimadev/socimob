#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html
git pull origin master
echo "=== DEPLOY CONCLUÍDO ==="
echo "Arquivos atualizados:"
echo "- ChavesNaMaoWebhookController.php"
echo "- LeadObserver.php"
