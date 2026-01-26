# 🔧 Comandos para Corrigir Servidor - exclusivalarimoveis.com

## 1️⃣ Conectar via SSH

```bash
plink -P 65002 u815655858@145.223.105.168
# Senha: MundoMelhor@10
```

## 2️⃣ Navegar para o diretório correto

```bash
cd /home/u815655858/domains/lojadaesquina.store/public_html
pwd
ls -la
```

## 3️⃣ Verificar se index.html existe

```bash
ls -lh index.html
ls -lh assets/
```

**Se index.html NÃO existir**, você precisa fazer upload do build:
- Local: Execute `cd client && pnpm build`
- Depois use FTP ou o script deploy-ssh.py para subir os arquivos

## 4️⃣ Criar/Atualizar .htaccess

```bash
cat > .htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Força sem www
    RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
    RewriteRule ^(.*)$ https://%1/$1 [R=301,L]
    
    # Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    
    # Arquivos existentes servem direto
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    
    # API vai para Lumen
    RewriteCond %{REQUEST_URI} ^/(api|webhook|debug|test) [NC]
    RewriteRule ^ index.php [L]
    
    # SPA fallback
    RewriteRule ^ index.html [L]
</IfModule>
EOF
```

## 5️⃣ Ajustar Permissões

```bash
chmod 644 .htaccess
chmod 644 index.html
chmod -R 755 assets
```

## 6️⃣ Limpar Cache

```bash
php artisan cache:clear
php artisan route:clear
```

## 7️⃣ Testar

Abra no navegador:
- https://exclusivalarimoveis.com/ (deve carregar React)
- https://exclusivalarimoveis.com/api/health (deve retornar JSON)
- https://lojadaesquina.store/ (deve carregar React)

---

## 🚀 Alternativa: Deploy Automático (se o build existir localmente)

Se você tem o build em `dist/public/`, execute no PowerShell:

```powershell
# 1. Fazer build
cd c:\Projetos\socimobatual\client
pnpm build

# 2. Copiar para public/
cd ..
robocopy dist\public public /E

# 3. Commit e push
git add dist public
git commit -m "build: production build for deploy"
git push

# 4. No servidor, fazer pull
# (conecte via SSH primeiro)
cd /home/u815655858/domains/lojadaesquina.store/public_html
git pull origin master
```

---

## ⚠️ Troubleshooting

### Se ainda der 404:
1. Verifique document root no painel de controle do host
2. Deve apontar para: `/home/u815655858/domains/lojadaesquina.store/public_html`
3. Ou se aponta para outro lugar, mova os arquivos para lá

### Se .htaccess não funcionar:
1. Verifique se mod_rewrite está ativo: `php -m | grep rewrite`
2. Verifique se AllowOverride está ativo no Apache config

### Ver logs de erro:
```bash
tail -100 /home/u815655858/domains/lojadaesquina.store/public_html/storage/logs/lumen-*.log
```
