#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html
git reset --hard
git pull
echo "=== ÚLTIMOS LOGS ==="
tail -200 storage/logs/laravel.log | grep -E "(🖼️|📥|✅|❌|Twilio|downloadAndSaveMedia|handleIncomingDocument)" || tail -50 storage/logs/laravel.log
