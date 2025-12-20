# 🚀 Scripts de Deploy - Exclusiva SaaS

Esta pasta contém scripts para automatizar o processo de deploy e configuração do sistema.

## 📁 Scripts Disponíveis

### 🔧 `first-deploy.sh` / `first-deploy.bat`
**Configuração completa do primeiro deploy**
- Instala dependências (composer)
- Executa migrações
- **Roda seeders automaticamente** (apenas no primeiro deploy)
- Configura permissões e cache
- Cria marcador `.first-deploy-done`

```bash
# Linux/Mac
./scripts/first-deploy.sh

# Windows
scripts\first-deploy.bat
```

### ✅ `verify-deploy.sh`
**Verificação pós-deploy**
- Testa conexão com banco
- Verifica se seeders foram executados
- Confirma criação de usuários e tenant
- Valida permissões de arquivos
- Testa servidor web (se disponível)

```bash
./scripts/verify-deploy.sh
```

## 🎯 Fluxo Recomendado

### Para Novo Ambiente:
1. **Configurar .env** com dados do banco
2. **Executar primeiro deploy**: `./scripts/first-deploy.sh`
3. **Verificar resultado**: `./scripts/verify-deploy.sh`
4. **Iniciar servidor**: `php -S 127.0.0.1:8000 -t public`

### Para Deploy Subsequente:
1. **Atualizar código** (git pull, etc.)
2. **Executar primeiro deploy**: `./scripts/first-deploy.sh` (seeders são pulados automaticamente)
3. **Verificar**: `./scripts/verify-deploy.sh`

## 🌱 Seeders no Primeiro Deploy

Os scripts automaticamente executam os seeders **apenas no primeiro deploy**, criando:

### 🏢 **Tenant Exclusiva**
- Nome: Exclusiva Imóveis
- Domain: exclusiva.localhost
- Plano Premium ativo

### 👥 **Usuários Iniciais**
- **Super Admin**: admin@exclusiva.com / `password`
- **Admin**: contato@exclusiva.com.br / `Teste@123`  
- **Alexsandra**: alexsandra@exclusiva.com.br / `Senha@123`
- **Marcus**: marcus@exclusiva.com.br / `Dev@123`
- **Corretor**: corretor@exclusiva.com.br / `Corretor@123`

## 🔄 Detecção de Deploy Subsequente

O sistema usa um arquivo `.first-deploy-done` para detectar se os seeders já foram executados:
- **Primeiro deploy**: Arquivo não existe → seeders são executados
- **Deploys seguintes**: Arquivo existe → seeders são pulados

Para **forçar execução dos seeders novamente**:
```bash
rm .first-deploy-done
./scripts/first-deploy.sh
```

## 🐛 Troubleshooting

### Script não executa
```bash
# Dar permissão de execução (Linux/Mac)
chmod +x scripts/*.sh
```

### Erro de banco de dados
1. Verificar se MySQL está rodando
2. Conferir credenciais no `.env`
3. Confirmar que banco `exclusiva` existe

### Erro de permissões
```bash
# Corrigir permissões (Linux/Mac)
chmod -R 775 storage bootstrap/cache

# Windows: executar como Administrador
```

### Seeders não executaram
```bash
# Executar manualmente
php database/seeders/DatabaseSeeder.php

# Ou forçar primeiro deploy
rm .first-deploy-done
./scripts/first-deploy.sh
```

## 📋 Logs e Debug

- **Logs do sistema**: `storage/logs/`
- **Output dos scripts**: Console durante execução
- **Verificação**: `./scripts/verify-deploy.sh`

## 🔗 Integração com CI/CD

Os scripts são integrados com:
- **GitHub Actions**: `.github/workflows/hostinger-deploy.yml`
- **Deploy manual**: Execução local/servidor

Ver [docs/DEPLOY_HOSTINGER.md](../docs/DEPLOY_HOSTINGER.md) para mais detalhes.