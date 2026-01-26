# 🚀 Padrão de Deploy - Build Local + Push via Git/SSH

## 📋 Visão Geral

SOCIMOB agora segue este padrão de deployment:

1. **Build Local**: React buildado localmente (`pnpm build`)
2. **Commit no Git**: Arquivos buildados commitados no repositório
3. **Deploy SSH**: Sincronização via SSH/rsync para produção
4. **Remote Setup**: Migrations e cache clear no servidor

---

## 🔄 Workflow de Desenvolvimento

### Desenvolvimento Local (Dev)

```bash
# Terminal 1: Backend (PHP)
php -S 127.0.0.1:8000 -t public router.php

# Terminal 2: Frontend (React Dev Server)
cd client && pnpm dev
# Acessa em http://localhost:3000
# API proxied para http://127.0.0.1:8000
```

### Build para Staging/Produção

```bash
# Build React localmente
cd client
pnpm install --frozen-lockfile  # Lock deps
pnpm build                       # Gera dist/public/

# Copiar para public/ (onde PHP serve)
cd ..
robocopy dist\public public /E  # Windows
# ou: cp -r dist/public/* public/  (Linux/Mac)

# Verificar mudanças
git status
# Deveria ver: modified dist/public/*, modified public/*

# Commit
git add .
git commit -m "build: react production build 2026-01-26"

# Push para GitHub
git push origin master
```

---

## 🖥️ Deploy para Produção (via SSH)

### Opção 1: Script Python (Recomendado)

```bash
# Fazer deploy com Python
python deploy-ssh.py \
  --host lojadaesquina.store \
  --user u815655858 \
  --env prod
```

**O que faz automaticamente**:
1. ✅ Build React localmente
2. ✅ Copia para `public/`
3. ✅ Otimiza autoloader PHP
4. ✅ Faz upload via rsync (incremental)
5. ✅ Executa migrations remotas
6. ✅ Limpa cache remotamente

### Opção 2: Script Bash

```bash
# Linux/Mac/WSL
bash build-and-deploy.sh
```

### Opção 3: Manual via SSH

```bash
# SSH para o servidor
ssh u815655858@lojadaesquina.store

# Dentro do servidor
cd /home/u815655858/public_html

# Puxar código
git pull origin master

# Migrations
php artisan migrate --force

# Clear cache
php artisan cache:clear

# Permissions
chmod -R 775 storage bootstrap/cache

# Exit
exit
```

---

## 📂 Estrutura de Pastas

### Localmente

```
socimobatual/
├── client/              # React source (Vite)
│   ├── src/
│   ├── package.json
│   ├── vite.config.ts
│   └── dist/            # Gerado pelo pnpm build
│       └── public/      # Saída: index.html + assets/
│
├── dist/                # Symlink/cópia de client/dist
│   └── public/          # (root level, criado por vite)
│
├── public/              # 🎯 Servido pelo PHP (wwwroot)
│   ├── index.html       # Copiado de dist/public/index.html
│   ├── assets/          # Copiado de dist/public/assets/
│   ├── app/             # Legacy HTML/jQuery (arquivado)
│   ├── portal/          # Portal cliente
│   └── index.php        # Lumen entry point
│
├── app/                 # PHP backend
├── routes/              # Rotas PHP
├── storage/             # Logs, cache
└── .github/             # CI/CD workflows
```

### No Servidor (Produção)

```
/home/u815655858/public_html/
├── public/              # 🎯 Servido por Apache
│   ├── index.html       # React SPA
│   ├── assets/          # CSS, JS, fonts
│   ├── index.php        # Fallback Lumen
│   └── ...
├── app/
├── bootstrap/
└── ... (resto do projeto)
```

---

## 🔐 Configuração SSH (Primeira Vez)

### 1. Gerar chave SSH local (se não tiver)

```bash
# Windows PowerShell
ssh-keygen -t ed25519 -C "seu@email.com"

# Aceitar defaults e definir senha
# Arquivos gerados:
# ~/.ssh/id_ed25519 (private key)
# ~/.ssh/id_ed25519.pub (public key)
```

### 2. Adicionar chave pública ao servidor

```bash
# Copiar a chave
cat ~/.ssh/id_ed25519.pub

# SSH para o servidor (vai pedir password ainda)
ssh u815655858@lojadaesquina.store

# Adicionar a chave
mkdir -p ~/.ssh
cat >> ~/.ssh/authorized_keys
# Colar a chave copiada
# Ctrl+D para finalizar

# Ajustar permissões
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys

# Exit
exit
```

### 3. Testar sem password

```bash
ssh u815655858@lojadaesquina.store "echo OK"
# Deveria printar "OK" sem pedir senha
```

---

## 📊 Workflow Visual

```
┌─────────────────────────────────────────────────────────────┐
│  DESENVOLVIMENTO LOCAL                                      │
│  Terminal 1: php -S 8000                                    │
│  Terminal 2: pnpm dev (React + HMR em 3000)                 │
│  Editam-se arquivos e muda reflete na hora                 │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  BUILD PRODUCTION                                           │
│  $ cd client && pnpm build                                  │
│  $ cp -r dist/public/* ../public/                           │
│  Gera: index.html, assets/*.{js,css}                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  COMMIT + PUSH                                              │
│  $ git add .                                                │
│  $ git commit -m "build: react..."                          │
│  $ git push origin master                                   │
│  GitHub recebe os arquivos buildados                        │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  DEPLOY SSH                                                 │
│  $ python deploy-ssh.py --host ... --user ...               │
│  1. rsync local → remote                                    │
│  2. php artisan migrate --force                             │
│  3. php artisan cache:clear                                 │
│  4. chmod 775 storage/ bootstrap/cache/                     │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  PRODUÇÃO ONLINE                                            │
│  Apache serve: /home/u815655858/public_html/public/        │
│  ✅ App pronta em: https://lojadaesquina.store              │
└─────────────────────────────────────────────────────────────┘
```

---

## ✅ Checklist Pré-Deploy

- [ ] Build local OK: `cd client && pnpm build` sucesso
- [ ] Testes: `php test_api_simples.php` passa
- [ ] Git clean: `git status` mostra o que será commitado
- [ ] SSH funciona: `ssh user@host "echo OK"` retorna OK
- [ ] Espaço em disco: `ssh user@host "df -h"` tem espaço

---

## 🔍 Troubleshooting

### Build não aparece em `public/`

```bash
# Verificar se foi criado
ls -la client/dist/public/

# Copiar manualmente
cp -r client/dist/public/* public/

# No Windows (PowerShell)
Copy-Item -Path 'client/dist/public/*' -Destination 'public/' -Recurse -Force
```

### SSH timeout

```bash
# Aumentar timeout
ssh -o ConnectTimeout=30 user@host

# Verificar firewall (porta 22 aberta)
# ou mudar para porta alternativa em .ssh/config
```

### Migrations falham remotamente

```bash
# SSH para o servidor
ssh user@host

# Verificar banco de dados
php artisan tinker
>>> DB::connection()->getPdo();  // Deveria conectar

# Testar migration específica
php artisan migrate:status
```

### Cache/Storage sem permissão

```bash
# SSH para o servidor
ssh user@host

# Dar permissão completa
chmod -R 777 storage bootstrap/cache

# Ou mais seguro (se tiver acesso aos grupos)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 755 storage bootstrap/cache
```

---

## 🎯 Próximos Passos

1. **GitHub Actions**: Implementar CI/CD para build automático
2. **Staging**: Criar workflow staging antes de prod
3. **Rollback**: Documentar como reverter um deploy
4. **Monitoring**: Alertas de erro pós-deploy
5. **Backup**: Backup automático antes de deploy

---

## 📚 Referências

- Vite Build Config: `client/vite.config.ts`
- PHP Router: `router.php` (serve React + API)
- Deploy Scripts: `build-and-deploy.sh`, `deploy-ssh.py`
- SSH Config: `~/.ssh/config` ou `~/.ssh/authorized_keys`

---

**Última atualização**: 26/01/2026
**Status**: ✅ Em Uso
