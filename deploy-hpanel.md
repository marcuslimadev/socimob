# Deploy Manual via hPanel (Hostinger)

## Quando usar
- Quando SSH/Git estiver indisponível
- Para deploys emergenciais
- Quando problemas de conectividade impedem deploy automático

## Passos

### 1. Build Local
```powershell
cd client
pnpm build
cd ..
Copy-Item -Path dist/public/* -Destination public/ -Recurse -Force
```

### 2. Acessar hPanel
1. Ir para https://hpanel.hostinger.com
2. Login: **exclusiva@exclusivalarimoveis.com**
3. Password: (verificar em .env se necessário)

### 3. File Manager
1. Clicar em **Files** → **File Manager**
2. Navegar até: `domains/lojadaesquina.store/public_html/`

### 4. Fazer Backup
1. Selecionar `public/index.html`
2. Download (backup local)
3. Renomear servidor: `index.html.bak` (opcional)

### 5. Upload Novos Arquivos
**Upload via interface:**
1. Ir para pasta `public/`
2. Upload → Selecionar:
   - `public/index.html` (local)
   - `public/assets/index-CSfMhesZ.js` (local)
   - `public/assets/index-D2oTfvb9.css` (local)
3. Confirmar sobrescrita

**Ou via ZIP:**
1. Local: Compactar `public/index.html` + `public/assets/*` em `deploy.zip`
2. Upload `deploy.zip` para `public/`
3. Extract no File Manager
4. Deletar `deploy.zip`

### 6. Verificação
1. Abrir: https://lojadaesquina.store/system-logs
2. CTRL+SHIFT+R (hard refresh)
3. Verificar console JavaScript (F12) por erros
4. Testar login admin

### 7. Rollback (se necessário)
1. Restaurar `index.html.bak`
2. Deletar novos assets
3. Hard refresh

## Arquivos Críticos Atuais
```
public/index.html           # 359 KB - Referências aos assets
public/assets/index-CSfMhesZ.js   # 1.23 MB - Bundle React principal
public/assets/index-D2oTfvb9.css  # 153 KB - Estilos TailwindCSS
```

## Verificação Rápida
```bash
# Via SSH (quando disponível)
ssh exclusiva@145.223.105.168 -p 65002
cd ~/domains/lojadaesquina.store/public_html/public
ls -lh index.html assets/index-*.js assets/index-*.css
grep -o 'index-[^"]*' index.html
```

## Notas
- **Build hash muda** a cada compilação (ex: `CSfMhesZ` → `ABC123XY`)
- Sempre verificar que `index.html` referencia assets corretos
- Deletar assets antigos após confirmar funcionamento
- Cache de CDN pode levar 5-10min para propagar
