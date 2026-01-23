# 🚀 Deploy Manual SSH - Guia Rápido

## Conectar ao Servidor

```bash
ssh -p 65002 u815655858@145.223.105.168
# Senha: MundoMelhor@10
```

## Executar Deploy (Copie e Cole Tudo)

```bash
cd ~/domains/lojadaesquina.store/public_html

# Git Pull
echo '=== GIT PULL ==='
git pull origin master
echo ''

# Copiar Build React (se existir)
echo '=== COPIAR BUILD REACT ==='
if [ -d "dist/public" ]; then
    cp -r dist/public/* public/ 2>/dev/null
    echo 'Build React copiado para public/'
fi
ls -lh public/index.html 2>/dev/null && echo '✓ Frontend React OK'
echo ''

# Criar estrutura de arquivo
echo '=== CRIAR PASTA ARQUIVO ==='
mkdir -p arquivo/docs arquivo/scripts arquivo/frontend-antigo 2>/dev/null
echo 'Pastas criadas'
echo ''

# Mover documentações
echo '=== MOVER DOCUMENTAÇÕES ==='
mv *.md arquivo/docs/ 2>/dev/null || true
mv DEPLOY_* GUIA_* RELATORIO_* PROBLEMA_* SOLUCAO_* PR_* arquivo/docs/ 2>/dev/null || true
echo 'Documentações movidas'
echo ''

# Mover scripts de diagnóstico/teste
echo '=== MOVER SCRIPTS ==='
mv check_*.php test_*.php test_*.ps1 create_*.php criar_*.php arquivo/scripts/ 2>/dev/null || true
mv fix_*.php debug_*.php diagnose_*.php update_*.php verificar_*.php arquivo/scripts/ 2>/dev/null || true
mv import*.php gerar_*.php reset_*.php setup_*.php testar_*.php teste_*.php arquivo/scripts/ 2>/dev/null || true
mv *.ps1 arquivo/scripts/ 2>/dev/null || true
echo 'Scripts movidos'
echo ''

# Mover frontends antigos
echo '=== MOVER FRONTENDS ANTIGOS ==='
mv portal-svelte conversor ethereal old temp-repo arquivo/frontend-antigo/ 2>/dev/null || true
echo 'Frontends antigos arquivados'
echo ''

# Mover arquivos de configuração antigos
echo '=== MOVER CONFIGS ANTIGAS ==='
mv *.sql *.txt *.zip *.tar.gz *.json arquivo/docs/ 2>/dev/null || true
echo 'Configs antigas movidas'
echo ''

# Usar PHP 8.3
echo '=== USAR PHP 8.3 ==='
/opt/alt/php83/usr/bin/php -v | head -1
/opt/alt/php83/usr/bin/php composer.phar dump-autoload --optimize 2>/dev/null || composer dump-autoload --optimize
echo ''

# Permissões
echo '=== AJUSTAR PERMISSÕES ==='
chmod -R 755 storage bootstrap/cache public 2>/dev/null
chmod -R 775 storage 2>/dev/null
echo 'Permissões ajustadas'
echo ''

# Estrutura final
echo '=== ESTRUTURA FINAL DA RAIZ ==='
ls -1 | head -30
echo ''

echo '=== CONTEÚDO DE ARQUIVO/ ==='
ls -l arquivo/
echo ''

# Testar
echo '=== TESTAR FRONTEND ==='
curl -s http://lojadaesquina.store/ | grep -o '<title>[^<]*</title>'
echo ''

echo '=== TESTAR API ==='
curl -s http://lojadaesquina.store/api/health
echo ''

echo '=== DEPLOY CONCLUÍDO ==='
date
```

## Estrutura Esperada Após Deploy

```
public_html/
├── app/              # Backend (Lumen controllers, models, etc)
├── bootstrap/        # Laravel/Lumen bootstrap
├── client/           # Fonte React (desenvolvimento)
├── config/           # Configurações PHP
├── database/         # Migrations e seeders
├── dist/             # Build temporário do React
├── public/           # 🌟 SERVIDO PELO SERVIDOR
│   ├── index.html    # ← Frontend React (build)
│   ├── index.php     # ← Backend Lumen (API)
│   ├── assets/       # ← Assets React
│   ├── app/          # ← Admin legado (HTML/jQuery)
│   └── ...
├── routes/           # Rotas da API
├── storage/          # Logs e cache
├── vendor/           # Dependências PHP
├── arquivo/          # 📁 ARQUIVADOS
│   ├── docs/         # Documentações (*.md, guias, etc)
│   ├── scripts/      # Scripts de teste/debug
│   └── frontend-antigo/ # Svelte, conversor, etc
├── .env              # Configuração de produção
├── composer.json     # Dependências PHP
├── package.json      # Dependências Node
└── router.php        # Router para servidor único
```

## Arquivos Essenciais na Raiz (Mantidos)

- `app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `storage/`, `vendor/` (core backend)
- `client/`, `dist/` (frontend React)
- `public/` (servido publicamente)
- `.env`, `.gitignore`, `composer.json`, `package.json` (configs)
- `artisan`, `router.php` (executáveis)
- `START.bat` (script de inicialização)

## Arquivos Movidos para arquivo/ (Organizados)

### arquivo/docs/
- Todos os `*.md` (documentações)
- `DEPLOY_*`, `GUIA_*`, `RELATORIO_*`, `PROBLEMA_*`, `SOLUCAO_*`
- Arquivos `.sql`, `.txt`, `.zip`, `.tar.gz`, `.json` antigos

### arquivo/scripts/
- `check_*.php`, `test_*.php`, `create_*.php`
- `fix_*.php`, `debug_*.php`, `diagnose_*.php`
- Scripts PowerShell `*.ps1`

### arquivo/frontend-antigo/
- `portal-svelte/`, `conversor/`, `ethereal/`
- `old/`, `temp-repo/`

## Comandos Úteis

```bash
# Ver tamanho das pastas
du -sh arquivo/*

# Verificar frontend React
ls -lh public/index.html public/assets/

# Verificar backend
ls -lh public/index.php app/Http/Controllers/

# Logs
tail -50 storage/logs/lumen-*.log

# PHP 8.3 (padrão para comandos)
/opt/alt/php83/usr/bin/php artisan --version
```

## Pós-Deploy

1. **Testar frontend**: https://lojadaesquina.store/
2. **Testar API**: https://lojadaesquina.store/api/health
3. **Verificar logs**: `tail storage/logs/lumen-*.log`
4. **Confirmar PHP 8.3**: `php -v`
