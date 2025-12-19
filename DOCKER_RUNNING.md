# 🐳 Exclusiva SaaS - Docker em Execução

## ✅ Status Atual

Todos os containers estão rodando com sucesso:

- **exclusiva-app**: http://localhost:8080 ✅
- **exclusiva-db**: localhost:3307 (MySQL 8.0) ✅
- **exclusiva-redis**: localhost:6379 (Redis 7) ✅

## 📋 Próximos Passos para Configuração Completa

### 1. Instalar Dependências PHP (Composer)

```powershell
# Entrar no container
docker exec -it exclusiva-app sh

# Dentro do container, instalar composer
cd /var/www/exclusiva
composer install

# Ou instalar sem dev dependencies
composer install --no-dev --optimize-autoloader
```

### 2. Configurar Banco de Dados

O arquivo `.env` já está configurado em `c:\Projetos\saas\backend\.env` com:

```env
DB_HOST=db
DB_PORT=3306
DB_DATABASE=exclusiva
DB_USERNAME=exclusiva
DB_PASSWORD=exclusiva
```

### 3. Executar Migrations

```powershell
# Executar migrations
docker exec exclusiva-app php artisan migrate

# Ou com force (produção)
docker exec exclusiva-app php artisan migrate --force
```

### 4. Popular Banco com Dados Iniciais

```powershell
# Entrar no tinker
docker exec -it exclusiva-app php artisan tinker

# Criar primeiro tenant (dentro do tinker)
$tenant = App\Models\Tenant::create([
    'name' => 'Imobiliária Teste',
    'domain' => 'localhost',
    'slug' => 'teste',
    'theme' => 'classico',
    'is_active' => true,
    'subscription_status' => 'active'
]);
```

## 🔧 Comandos Úteis

### Gerenciar Containers

```powershell
# Ver status
docker compose ps

# Ver logs em tempo real
docker logs exclusiva-app -f

# Parar todos os containers
docker compose down

# Reiniciar
docker compose restart

# Reconstruir
docker compose up -d --build
```

### Acessar Container

```powershell
# Shell do container da aplicação
docker exec -it exclusiva-app sh

# Shell do MySQL
docker exec -it exclusiva-db mysql -u exclusiva -pexclusiva

# Shell do Redis
docker exec -it exclusiva-redis redis-cli
```

### Executar Comandos PHP/Artisan

```powershell
# Limpar cache
docker exec exclusiva-app php artisan cache:clear

# Listar rotas
docker exec exclusiva-app php artisan route:list

# Criar migration
docker exec exclusiva-app php artisan make:migration create_exemplo_table

# Executar comando customizado
docker exec exclusiva-app php artisan seu:comando
```

## 🗄️ Conexão com Banco de Dados

Use estas credenciais para conectar ferramentas como DBeaver, MySQL Workbench, etc:

- **Host**: localhost
- **Porta**: 3307
- **Database**: exclusiva
- **Usuário**: exclusiva
- **Senha**: exclusiva

## 🔴 Redis

Conectar ao Redis:

```powershell
docker exec -it exclusiva-redis redis-cli

# Comandos Redis úteis
PING
KEYS *
GET chave
SET chave valor
```

## 📁 Estrutura de Arquivos

```
c:\Projetos\saas\
├── backend/          → Montado em /var/www/exclusiva (container)
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── public/
│   ├── bootstrap/
│   └── .env
└── docker/
    ├── docker-compose.yml
    ├── Dockerfile.simple
    ├── nginx.conf
    └── entrypoint-simple.sh
```

## 🐛 Troubleshooting

### Container não inicia

```powershell
# Ver logs
docker logs exclusiva-app

# Reconstruir
docker compose down
docker compose up -d --build
```

### Erro de permissão

```powershell
# Ajustar permissões
docker exec exclusiva-app chown -R www-data:www-data /var/www/exclusiva
docker exec exclusiva-app chmod -R 755 /var/www/exclusiva
```

### Banco de dados não conecta

```powershell
# Verificar se MySQL está rodando
docker exec exclusiva-db mysqladmin ping

# Ver logs do MySQL
docker logs exclusiva-db
```

## 🚀 Deploy em Produção

Para deploy em produção (AWS), consulte:
- `docs/FASE7_AWS_INFRASTRUCTURE.md`
- `docker/GUIA_DOCKER_AWS.md`

## 📞 Suporte

Para mais informações, consulte a documentação completa em `docs/`.
