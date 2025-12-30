# 🎤 Guia de Instalação do FFmpeg e Correção de Transcrição de Áudio

## ✅ Status Atual

### O que já está funcionando:
- ✅ Webhook recebendo mensagens de áudio
- ✅ Detecção correta do tipo de mensagem (`audio`)
- ✅ **Credenciais OpenAI e Twilio configuradas no banco de dados**
- ✅ Sistema enviando feedback "🎤 Recebi seu áudio! Vou ouvir agora..."

### O que falta:
- ❌ **FFmpeg não instalado no servidor**

---

## 📦 Instalação do FFmpeg

Como você está em um servidor compartilhado (Hostinger) sem acesso root, vamos instalar um binário estático no seu diretório pessoal.

### Passo 1: Fazer Pull do Repositório

```bash
cd ~/public_html
git pull
```

### Passo 2: Executar Script de Instalação

```bash
bash install_ffmpeg_shared.sh
```

O script irá:
1. Baixar FFmpeg estático (binário compilado)
2. Extrair para `~/bin/ffmpeg`
3. Dar permissão de execução
4. Testar a instalação

### Passo 3: Verificar Instalação

```bash
~/bin/ffmpeg -version
```

Deve retornar algo como:
```
ffmpeg version N-XXXXX-g... Copyright (c) 2000-2025 the FFmpeg developers
```

---

## 🧪 Teste Final

Após a instalação do FFmpeg:

1. **Envie um áudio** pelo WhatsApp para **+553173341150**

2. **O que deve acontecer:**
   - ✅ Sistema detecta áudio
   - ✅ Envia: "🎤 Recebi seu áudio! Vou ouvir agora e já te respondo... ⏳"
   - ✅ Baixa o áudio do Twilio
   - ✅ Converte OGG → MP3 usando `~/bin/ffmpeg`
   - ✅ Transcreve com OpenAI Whisper
   - ✅ Teresa processa a transcrição
   - ✅ Responde ao usuário

---

## 🔧 Como Funciona

O código em `app/Services/WhatsAppService.php` já está configurado para procurar FFmpeg em vários locais:

```php
$alternativePaths = [
    getenv('HOME') . '/bin/ffmpeg',  // ← Este será usado!
    '/usr/bin/ffmpeg',
    '/usr/local/bin/ffmpeg',
    base_path('ffmpeg')
];
```

Quando você instala em `~/bin/ffmpeg`, o sistema encontra automaticamente! 🎯

---

## ❓ Troubleshooting

### Se ainda der erro após instalação:

1. **Verificar permissões:**
   ```bash
   ls -la ~/bin/ffmpeg
   chmod +x ~/bin/ffmpeg
   ```

2. **Testar conversão manual:**
   ```bash
   ~/bin/ffmpeg -i input.ogg -ar 44100 -ac 2 -b:a 192k output.mp3
   ```

3. **Verificar logs:**
   ```bash
   tail -f ~/public_html/storage/logs/lumen-$(date +%Y-%m-%d).log
   ```

---

## 📊 Resumo da Correção

| Item | Status Anterior | Status Atual |
|------|----------------|--------------|
| OpenAI API Key | ❌ Não configurado | ✅ Configurado no banco |
| Twilio Credentials | ❌ Não configurado | ✅ Configurado no banco |
| FFmpeg | ❌ Não instalado | ⏳ Aguardando instalação |
| Detecção de Áudio | ✅ Funcionando | ✅ Funcionando |
| Download de Áudio | ✅ Funcionando | ✅ Funcionando |
| Conversão OGG→MP3 | ❌ FFmpeg ausente | ⏳ Após instalação |
| Transcrição OpenAI | ❌ Falha | ⏳ Após instalação |

---

## 🚀 Execução

**No servidor SSH:**

```bash
cd ~/public_html
git pull
bash install_ffmpeg_shared.sh
```

**Depois envie um áudio e teste!** 🎉
