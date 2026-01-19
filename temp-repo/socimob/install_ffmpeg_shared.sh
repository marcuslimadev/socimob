#!/bin/bash

# Script para instalar FFmpeg em servidor compartilhado (sem root)
# Para Hostinger/cPanel com acesso SSH limitado

echo "╔═══════════════════════════════════════════════════════════════════╗"
echo "║     📦 INSTALAÇÃO DO FFMPEG - Servidor Compartilhado              ║"
echo "╚═══════════════════════════════════════════════════════════════════╝"
echo ""

# Detectar arquitetura
ARCH=$(uname -m)
echo "🔍 Arquitetura detectada: $ARCH"
echo ""

# Diretório de instalação
INSTALL_DIR="$HOME/bin"
mkdir -p "$INSTALL_DIR"

cd /tmp

echo "📥 Baixando FFmpeg estático..."

if [ "$ARCH" = "x86_64" ]; then
    # 64-bit
    wget -q --show-progress https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz
    
    if [ $? -eq 0 ]; then
        echo "✅ Download completo!"
        echo ""
        echo "📦 Extraindo arquivos..."
        tar -xf ffmpeg-release-amd64-static.tar.xz
        
        # Encontrar o diretório extraído
        FFMPEG_DIR=$(find . -maxdepth 1 -type d -name "ffmpeg-*-amd64-static" | head -n 1)
        
        if [ -d "$FFMPEG_DIR" ]; then
            echo "✅ Arquivos extraídos!"
            echo ""
            echo "📋 Copiando binários para $INSTALL_DIR..."
            
            cp "$FFMPEG_DIR/ffmpeg" "$INSTALL_DIR/"
            cp "$FFMPEG_DIR/ffprobe" "$INSTALL_DIR/"
            
            chmod +x "$INSTALL_DIR/ffmpeg"
            chmod +x "$INSTALL_DIR/ffprobe"
            
            # Limpar arquivos temporários
            rm -rf ffmpeg-*
            
            echo "✅ FFmpeg instalado com sucesso!"
            echo ""
            echo "📍 Localização: $INSTALL_DIR/ffmpeg"
            echo ""
            
            # Testar instalação
            echo "🧪 Testando instalação..."
            "$INSTALL_DIR/ffmpeg" -version | head -n 1
            
            echo ""
            echo "╔═══════════════════════════════════════════════════════════════════╗"
            echo "║     ✅ INSTALAÇÃO COMPLETA!                                       ║"
            echo "╚═══════════════════════════════════════════════════════════════════╝"
            echo ""
            echo "📝 Caminho do FFmpeg: $INSTALL_DIR/ffmpeg"
            echo ""
            echo "🔧 Adicione ao seu PATH (opcional):"
            echo "   echo 'export PATH=\"\$HOME/bin:\$PATH\"' >> ~/.bashrc"
            echo "   source ~/.bashrc"
            echo ""
            echo "🎉 Agora a transcrição de áudio deve funcionar!"
            
        else
            echo "❌ Erro ao extrair arquivos"
            exit 1
        fi
    else
        echo "❌ Erro no download"
        exit 1
    fi
else
    echo "❌ Arquitetura não suportada: $ARCH"
    echo "   Entre em contato com o suporte da Hostinger"
    exit 1
fi
