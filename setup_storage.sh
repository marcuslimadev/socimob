cd ~/domains/lojadaesquina.store/public_html
php artisan migrate --force
echo ""
echo "=== Criando link de storage ==="
cd ~/domains/lojadaesquina.store/public_html
if [ ! -e public/storage ]; then
    ln -s ../storage/app/public public/storage
    echo "Link criado com sucesso"
else
    echo "Link ja existe"
fi
ls -la public/storage
