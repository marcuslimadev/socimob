# 📦 Instruções de Instalação - Exclusiva SaaS

## 🚀 Passo 1: Extrair o ZIP

```bash
unzip exclusiva-saas-delivery.zip
cd exclusiva-saas-delivery
```

## 📂 Estrutura do Pacote

```
exclusiva-saas-delivery/
├── backend/
│   ├── app/
│   │   ├── Models/           # 5 novos modelos
│   │   ├── Services/         # 3 novos serviços
│   │   ├── Http/
│   │   │   ├── Controllers/  # 6 novos controllers
│   │   │   ├── Middleware/   # 2 novos middlewares
│   │   │   └── Traits/       # 1 novo trait
│   │   └── Traits/
│   ├── database/
│   │   └── migrations/       # 7 novas migrations
│   └── routes/               # 6 novos arquivos de rotas
├── docs/                      # Documentação completa
├── docker/                    # Arquivos Docker (opcional)
├── scripts/                   # Scripts de deployment
└── INSTRUCOES_INSTALACAO.md   # Este arquivo
```

## 🔧 Passo 2: Integrar com Repositório Existente

### 2.1 Copiar Modelos
```bash
cp backend/app/Models/*.php ../exclusiva/backend/app/Models/
```

### 2.2 Copiar Serviços
```bash
cp backend/app/Services/*.php ../exclusiva/backend/app/Services/
```

### 2.3 Copiar Controllers
```bash
cp -r backend/app/Http/Controllers/* ../exclusiva/backend/app/Http/Controllers/
```

### 2.4 Copiar Middlewares
```bash
cp backend/app/Http/Middleware/*.php ../exclusiva/backend/app/Http/Middleware/
```

### 2.5 Copiar Traits
```bash
cp backend/app/Traits/*.php ../exclusiva/backend/app/Traits/
```

### 2.6 Copiar Migrations
```bash
cp backend/database/migrations/*.php ../exclusiva/backend/database/migrations/
```

### 2.7 Copiar Rotas
```bash
cp backend/routes/*.php ../exclusiva/backend/routes/
```

## ⚙️ Passo 3: Registrar Rotas em bootstrap/app.php

Adicione as seguintes linhas ao arquivo `bootstrap/app.php`:

```php
// Registrar rotas de super admin
$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant']], function () use ($router) {
    require __DIR__ . '/../routes/super-admin.php';
    require __DIR__ . '/../routes/admin.php';
    require __DIR__ . '/../routes/subscriptions.php';
    require __DIR__ . '/../routes/themes.php';
    require __DIR__ . '/../routes/domains.php';
    require __DIR__ . '/../routes/client-portal.php';
});
```

## 🗄️ Passo 4: Executar Migrations

```bash
cd ../exclusiva/backend

# Instalar dependências (se necessário)
composer install

# Executar migrations
php artisan migrate
```

## 🧪 Passo 5: Testes Locais

### 5.1 Instalar dependências de teste
```bash
composer require --dev phpunit/phpunit
```

### 5.2 Executar testes
```bash
php artisan test
```

### 5.3 Testar endpoints
```bash
# Iniciar servidor
php artisan serve

# Em outro terminal, testar
curl -X GET http://localhost:8000/api/theme
```

## 🐳 Passo 6: Docker (Opcional)

Se preferir usar Docker:

```bash
# Construir imagem
docker build -t exclusiva-saas:latest .

# Executar container
docker run -p 8000:8000 exclusiva-saas:latest
```

## ☁️ Passo 7: Deploy na AWS

### 7.1 Preparar EC2
```bash
# Conectar na instância
ssh -i chave.pem ubuntu@<IP_EC2>

# Clonar repositório atualizado
git clone https://github.com/marcuslimadev/exclusiva.git
cd exclusiva/backend

# Instalar dependências
composer install --optimize-autoloader --no-dev

# Configurar .env
cp .env.example .env
# Editar variáveis de ambiente

# Executar migrations
php artisan migrate --force

# Gerar cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7.2 Configurar Nginx
```bash
# Copiar configuração
sudo cp nginx.conf /etc/nginx/sites-available/exclusiva.conf
sudo ln -s /etc/nginx/sites-available/exclusiva.conf /etc/nginx/sites-enabled/

# Testar configuração
sudo nginx -t

# Reiniciar
sudo systemctl restart nginx
```

### 7.3 Configurar SSL
```bash
# Let's Encrypt
sudo certbot certonly --nginx -d exclusiva.com.br

# Atualizar nginx.conf com certificados
```

### 7.4 Configurar CloudFront
```bash
# Criar distribuição CloudFront
aws cloudfront create-distribution --distribution-config file://cloudfront-config.json
```

### 7.5 Configurar Route 53
```bash
# Criar registros DNS
aws route53 change-resource-record-sets --hosted-zone-id <ZONE_ID> --change-batch file://route53-changes.json
```

## 📋 Checklist de Verificação

### Local
- [ ] Migrations executadas com sucesso
- [ ] Rotas registradas em bootstrap/app.php
- [ ] Testes passando
- [ ] API respondendo em localhost
- [ ] Banco de dados com dados de teste

### AWS
- [ ] EC2 instância criada
- [ ] RDS banco de dados criado
- [ ] Security groups configurados
- [ ] Código deployado
- [ ] Migrations executadas
- [ ] SSL configurado
- [ ] CloudFront distribuição criada
- [ ] Route 53 DNS configurado
- [ ] Monitoramento ativado

## 🐛 Troubleshooting

### Erro: "Class not found"
```bash
# Executar autoload
composer dump-autoload
```

### Erro: "Migration not found"
```bash
# Verificar migrations
php artisan migrate:status

# Resetar (cuidado em produção!)
php artisan migrate:reset
php artisan migrate
```

### Erro: "Permission denied"
```bash
# Ajustar permissões
sudo chown -R www-data:www-data /var/www/exclusiva
sudo chmod -R 755 /var/www/exclusiva
sudo chmod -R 775 /var/www/exclusiva/storage
```

## 📞 Suporte

Consulte a documentação em `docs/`:
- `RESUMO_EXECUTIVO_SAAS.md` - Visão geral do projeto
- `FASE7_AWS_INFRASTRUCTURE.md` - Detalhes de infraestrutura
- `FASE8_FINAL_TESTING_AND_DELIVERY.md` - Testes e manutenção

## ✅ Próximos Passos

1. ✅ Extrair ZIP
2. ✅ Integrar com repositório
3. ✅ Executar migrations
4. ✅ Rodar testes locais
5. ✅ Deploy na AWS
6. ✅ Configurar monitoramento

---

**Data:** 2025-12-18
**Versão:** 1.0.0
