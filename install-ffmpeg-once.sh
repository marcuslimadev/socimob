#!/bin/bash
# Script de instalação do FFmpeg (executar apenas uma vez)

INSTALL_DIR="$HOME/bin"
FFMPEG_PATH="$INSTALL_DIR/ffmpeg"

# Verificar se já existe
if [ -f "$FFMPEG_PATH" ] && [ -x "$FFMPEG_PATH" ]; then
    echo "✅ FFmpeg já instalado em $FFMPEG_PATH"
    "$FFMPEG_PATH" -version | head -n 1
    exit 0
fi

echo "📦 Instalando FFmpeg em $INSTALL_DIR..."

# Criar diretório
mkdir -p "$INSTALL_DIR"

# Baixar FFmpeg estático
cd /tmp
wget -q --show-progress https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz

# Extrair
tar -xf ffmpeg-release-amd64-static.tar.xz

# Copiar binário
FFMPEG_DIR=$(find . -maxdepth 1 -type d -name "ffmpeg-*-amd64-static" | head -n 1)
cp "$FFMPEG_DIR/ffmpeg" "$INSTALL_DIR/"
chmod 755 "$INSTALL_DIR/ffmpeg"

# Limpar
rm -rf ffmpeg-*

echo "✅ FFmpeg instalado com sucesso!"
"$INSTALL_DIR/ffmpeg" -version | head -n 1
