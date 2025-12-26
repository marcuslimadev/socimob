#!/bin/bash

# Script para instalar FFmpeg no servidor Hostinger
# Execute no servidor: bash install_ffmpeg.sh

cd ~/domains/lojadaesquina.store/public_html

echo "🔽 Baixando FFmpeg estático..."
curl -L https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -o ffmpeg.tar.xz

echo "📦 Extraindo (sem xz, usando workaround)..."
# Hostinger não tem xz, vamos tentar baixar o binário direto
rm -f ffmpeg.tar.xz

echo "🔽 Tentando baixar binário direto do GitHub..."
curl -L https://github.com/eugeneware/ffmpeg-static/releases/download/b6.0/ffmpeg-linux-x64 -o bin/ffmpeg

echo "✅ Verificando download..."
ls -lh bin/ffmpeg

echo "🔧 Dando permissão de execução..."
chmod +x bin/ffmpeg

echo "🔗 Criando symlink..."
ln -sf bin/ffmpeg ffmpeg

echo "✅ Testando FFmpeg..."
./bin/ffmpeg -version | head -3

echo "✨ Pronto!"
