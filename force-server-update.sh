#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html

echo "=== RESETANDO GIT ===" 
git config user.email "deploy@socimob.com"
git config user.name "Deploy Bot"
git reset --hard HEAD

echo "=== GIT PULL ==="
git pull origin master

echo "=== VERIFICANDO INDEX.HTML ===" 
head -20 index.html | grep "index-"

echo "=== LISTANDO JS FILES ==="
ls -lh assets/index-*.js | tail -5

echo "=== LIMPANDO CACHE ===" 
touch .htaccess

echo "=== DEPLOY ATUALIZADO ==="
date
