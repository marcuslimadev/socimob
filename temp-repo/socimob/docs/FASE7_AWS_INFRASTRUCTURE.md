# Fase 7: Preparação da Infraestrutura AWS (EC2, RDS, Route 53, CloudFront)

## 📋 Resumo Executivo

Nesta fase, documentamos toda a infraestrutura necessária para hospedar a plataforma Exclusiva SaaS na AWS, incluindo EC2 para a aplicação, RDS para o banco de dados, Route 53 para DNS e CloudFront para CDN.

---

## 🏗️ Arquitetura AWS

```
┌─────────────────────────────────────────────────────────────────┐
│                         INTERNET                                 │
└────────────────────────────┬────────────────────────────────────┘
                             │
                    ┌────────▼────────┐
                    │   Route 53      │
                    │   (DNS)         │
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  CloudFront     │
                    │  (CDN)          │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
   ┌────▼────┐          ┌────▼────┐          ┌────▼────┐
   │ EC2 AZ1 │          │ EC2 AZ2 │          │ EC2 AZ3 │
   │(Laravel)│          │(Laravel)│          │(Laravel)│
   └────┬────┘          └────┬────┘          └────┬────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
                    ┌────────▼────────┐
                    │   RDS Aurora    │
                    │   (MySQL)       │
                    │   Multi-AZ      │
                    └─────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
   ┌────▼────┐          ┌────▼────┐          ┌────▼────┐
   │ S3       │          │ ElastiCache│      │CloudWatch│
   │(Assets)  │          │ (Redis)    │      │(Logs)    │
   └──────────┘          └────────────┘      └──────────┘
```

---

## 🖥️ EC2 - Instância da Aplicação

### Especificações Recomendadas

| Aspecto | Valor | Justificativa |
|--------|-------|---------------|
| **Tipo de Instância** | t3.large | Boa relação custo-benefício, burst capable |
| **vCPU** | 2 | Suficiente para aplicação Laravel |
| **Memória RAM** | 8 GB | Adequado para PHP-FPM + MySQL |
| **Armazenamento** | 100 GB SSD (gp3) | Rápido e escalável |
| **Zona de Disponibilidade** | Multi-AZ | Alta disponibilidade |
| **Sistema Operacional** | Ubuntu 22.04 LTS | Suporte de longo prazo |
| **Rede** | VPC com Security Groups | Segurança de rede |

### Configuração de Segurança

#### Security Group - Entrada

| Porta | Protocolo | Origem | Descrição |
|-------|-----------|--------|-----------|
| 80 | HTTP | 0.0.0.0/0 | Tráfego HTTP |
| 443 | HTTPS | 0.0.0.0/0 | Tráfego HTTPS |
| 22 | SSH | IP Específico | Acesso administrativo |
| 3306 | MySQL | VPC CIDR | Comunicação com RDS |

#### Security Group - Saída

| Porta | Protocolo | Destino | Descrição |
|-------|-----------|---------|-----------|
| Todas | Todas | 0.0.0.0/0 | Saída geral |

### Software a Instalar

```bash
# Sistema
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip htop

# PHP 8.1
sudo apt install -y php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-xml php8.1-zip php8.1-mbstring

# Nginx
sudo apt install -y nginx

# MySQL Client
sudo apt install -y mysql-client

# Node.js (para build de assets)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Redis Client
sudo apt install -y redis-tools

# Supervisor (para jobs)
sudo apt install -y supervisor

# SSL (Let's Encrypt)
sudo apt install -y certbot python3-certbot-nginx
```

### Configuração de Nginx

```nginx
# /etc/nginx/sites-available/exclusiva.conf

upstream php_backend {
    server unix:/var/run/php/php8.1-fpm.sock;
}

server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/exclusiva/backend/public;
    index index.php;

    # Redirecionar HTTP para HTTPS
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name _;

    root /var/www/exclusiva/backend/public;
    index index.php;

    # SSL
    ssl_certificate /etc/letsencrypt/live/exclusiva.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/exclusiva.com.br/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Logs
    access_log /var/log/nginx/exclusiva_access.log;
    error_log /var/log/nginx/exclusiva_error.log;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1000;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;

    # Segurança
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # PHP
    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param SCRIPT_NAME $fastcgi_script_name;
    }

    # Rewrite para Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Arquivos estáticos
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Negar acesso a arquivos sensíveis
    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

### Configuração de PHP-FPM

```ini
# /etc/php/8.1/fpm/pool.d/www.conf

[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 2
pm.max_spare_servers = 10
pm.max_requests = 500

; PHP Settings
php_value[memory_limit] = 256M
php_value[max_execution_time] = 300
php_value[upload_max_filesize] = 100M
php_value[post_max_size] = 100M
```

### Deploy da Aplicação

```bash
# Clonar repositório
cd /var/www
git clone https://github.com/marcuslimadev/exclusiva.git
cd exclusiva/backend

# Instalar dependências
composer install --optimize-autoloader --no-dev

# Configurar permissões
sudo chown -R www-data:www-data /var/www/exclusiva
sudo chmod -R 755 /var/www/exclusiva
sudo chmod -R 775 /var/www/exclusiva/storage
sudo chmod -R 775 /var/www/exclusiva/bootstrap/cache

# Configurar .env
cp .env.example .env
# Editar .env com variáveis de produção

# Gerar chave da aplicação
php artisan key:generate

# Executar migrations
php artisan migrate --force

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar serviços
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx
```

---

## 🗄️ RDS - Banco de Dados

### Especificações Recomendadas

| Aspecto | Valor | Justificativa |
|--------|-------|---------------|
| **Engine** | MySQL 8.0 | Compatível com Laravel |
| **Classe de Instância** | db.t3.medium | Boa performance para início |
| **Armazenamento** | 100 GB SSD (gp3) | Escalável |
| **Multi-AZ** | Sim | Alta disponibilidade |
| **Backup** | 30 dias | Retenção adequada |
| **Encryption** | Sim | Dados criptografados |
| **Backup Automático** | Diário | Proteção de dados |

### Configuração de Segurança

#### Security Group

| Porta | Protocolo | Origem | Descrição |
|-------|-----------|--------|-----------|
| 3306 | TCP | Security Group EC2 | Acesso da aplicação |

#### Parâmetros de Banco de Dados

```sql
-- Character Set
character_set_client = utf8mb4
character_set_connection = utf8mb4
character_set_database = utf8mb4
character_set_results = utf8mb4
character_set_server = utf8mb4
collation_connection = utf8mb4_unicode_ci
collation_server = utf8mb4_unicode_ci

-- Performance
max_connections = 1000
slow_query_log = 1
long_query_time = 2
log_queries_not_using_indexes = 1

-- InnoDB
innodb_buffer_pool_size = 2GB
innodb_log_file_size = 512MB
```

### Backup e Recuperação

```bash
# Backup manual
aws rds create-db-snapshot \
    --db-instance-identifier exclusiva-db \
    --db-snapshot-identifier exclusiva-backup-$(date +%Y%m%d-%H%M%S)

# Listar snapshots
aws rds describe-db-snapshots \
    --db-instance-identifier exclusiva-db

# Restaurar de snapshot
aws rds restore-db-instance-from-db-snapshot \
    --db-instance-identifier exclusiva-db-restored \
    --db-snapshot-identifier exclusiva-backup-20251218-100000
```

---

## 🌐 Route 53 - DNS

### Configuração de Domínios

#### Domínio Principal

```
Domínio: exclusiva.com.br
Tipo de Registro: A
Valor: <IP Elástico do CloudFront>
TTL: 300

Domínio: www.exclusiva.com.br
Tipo de Registro: CNAME
Valor: exclusiva.com.br
TTL: 300
```

#### Subdomínios de Tenant

```
Domínio: *.exclusiva.com.br
Tipo de Registro: A
Valor: <IP Elástico do CloudFront>
TTL: 300

Exemplo:
- imobiliaria-joao.exclusiva.com.br → CloudFront
- imobiliaria-maria.exclusiva.com.br → CloudFront
```

#### Domínios Customizados

```
Domínio: imobiliaria-joao.com.br (do cliente)
Tipo de Registro: A
Valor: <IP Elástico do CloudFront>
TTL: 3600

OU

Domínio: imobiliaria-joao.com.br (do cliente)
Tipo de Registro: CNAME
Valor: d123456.cloudfront.net
TTL: 3600
```

### Health Check

```bash
# Criar health check
aws route53 create-health-check \
    --health-check-config \
    IPAddress=<IP_EC2>,\
    Port=443,\
    Type=HTTPS,\
    ResourcePath=/health,\
    FullyQualifiedDomainName=exclusiva.com.br
```

---

## 🚀 CloudFront - CDN

### Distribuição CloudFront

#### Origem

| Configuração | Valor |
|--------------|-------|
| **Domain Name** | EC2 Elastic IP ou ALB |
| **Protocol** | HTTPS |
| **Port** | 443 |
| **Origin Path** | / |

#### Comportamento

| Configuração | Valor |
|--------------|-------|
| **Path Pattern** | * |
| **Viewer Protocol Policy** | Redirect HTTP to HTTPS |
| **Allowed HTTP Methods** | GET, HEAD, OPTIONS, PUT, POST, PATCH, DELETE |
| **Cache Policy** | CachingDisabled (para API) |
| **Origin Request Policy** | AllViewerExceptHostHeader |

#### Comportamentos Específicos

```
# Comportamento 1: Assets (CSS, JS, Imagens)
Path Pattern: /assets/*
Cache Policy: CachingOptimized
TTL: 31536000 (1 ano)

# Comportamento 2: API
Path Pattern: /api/*
Cache Policy: CachingDisabled
Compress: Yes

# Comportamento 3: Temas CSS
Path Pattern: /api/theme/css
Cache Policy: CachingOptimized
TTL: 3600 (1 hora)
```

#### Segurança

```
# HTTPS
Minimum TLS Version: TLSv1.2_2021
Certificate: ACM Certificate

# Headers de Segurança
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000

# WAF
Enable AWS WAF: Yes
Web ACL: AWSManagedRulesCommonRuleSet
```

### Invalidação de Cache

```bash
# Invalidar tudo
aws cloudfront create-invalidation \
    --distribution-id <DISTRIBUTION_ID> \
    --paths "/*"

# Invalidar específico
aws cloudfront create-invalidation \
    --distribution-id <DISTRIBUTION_ID> \
    --paths "/api/theme/css" "/assets/*"
```

---

## 💾 S3 - Armazenamento de Assets

### Bucket S3

| Configuração | Valor |
|--------------|-------|
| **Nome** | exclusiva-assets |
| **Região** | us-east-1 |
| **Versionamento** | Habilitado |
| **Criptografia** | AES-256 |
| **Acesso Público** | Bloqueado |

### Política de Acesso

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::ACCOUNT_ID:role/EC2-Role"
            },
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::exclusiva-assets/*"
        }
    ]
}
```

### Acesso via CloudFront

```
URL Pública: https://d123456.cloudfront.net/assets/logo.png
URL S3: https://exclusiva-assets.s3.amazonaws.com/assets/logo.png
```

---

## 📊 CloudWatch - Monitoramento

### Métricas Principais

#### EC2
- CPU Utilization
- Network In/Out
- Disk Read/Write

#### RDS
- CPU Utilization
- Database Connections
- Disk Free Storage
- Read/Write Latency

#### CloudFront
- Requests
- Bytes Downloaded
- Error Rate (4xx, 5xx)

### Alarmes

```bash
# Alarme: CPU EC2 > 80%
aws cloudwatch put-metric-alarm \
    --alarm-name exclusiva-ec2-cpu-high \
    --alarm-description "EC2 CPU above 80%" \
    --metric-name CPUUtilization \
    --namespace AWS/EC2 \
    --statistic Average \
    --period 300 \
    --threshold 80 \
    --comparison-operator GreaterThanThreshold \
    --evaluation-periods 2

# Alarme: RDS Free Storage < 10GB
aws cloudwatch put-metric-alarm \
    --alarm-name exclusiva-rds-storage-low \
    --alarm-description "RDS storage below 10GB" \
    --metric-name FreeStorageSpace \
    --namespace AWS/RDS \
    --statistic Average \
    --period 300 \
    --threshold 10737418240 \
    --comparison-operator LessThanThreshold
```

### Logs

```bash
# CloudWatch Logs Group
/aws/ec2/exclusiva
/aws/rds/exclusiva
/aws/lambda/exclusiva

# Retenção
30 dias para logs normais
90 dias para logs de erro
```

---

## 🔐 IAM - Controle de Acesso

### Roles

#### EC2 Role

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::exclusiva-assets/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "rds:DescribeDBInstances"
            ],
            "Resource": "*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "logs:CreateLogGroup",
                "logs:CreateLogStream",
                "logs:PutLogEvents"
            ],
            "Resource": "arn:aws:logs:*:*:*"
        }
    ]
}
```

#### Lambda Role (para jobs)

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "rds:DescribeDBInstances",
                "rds-db:connect"
            ],
            "Resource": "*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "logs:CreateLogGroup",
                "logs:CreateLogStream",
                "logs:PutLogEvents"
            ],
            "Resource": "arn:aws:logs:*:*:*"
        }
    ]
}
```

---

## 📋 Checklist de Deployment

### Pré-Deployment
- [ ] Credenciais AWS configuradas
- [ ] Domínio registrado
- [ ] Certificado SSL obtido
- [ ] Variáveis de ambiente preparadas
- [ ] Banco de dados criado
- [ ] Backups configurados

### Deployment
- [ ] EC2 instância criada
- [ ] Software instalado
- [ ] Aplicação clonada
- [ ] Dependências instaladas
- [ ] .env configurado
- [ ] Migrations executadas
- [ ] Assets compilados
- [ ] Nginx configurado
- [ ] SSL configurado
- [ ] CloudFront distribuição criada
- [ ] Route 53 DNS configurado
- [ ] Health checks ativados

### Pós-Deployment
- [ ] Testar acesso via HTTPS
- [ ] Testar API endpoints
- [ ] Testar autenticação
- [ ] Testar multi-tenant
- [ ] Testar assinaturas
- [ ] Testar notificações
- [ ] Monitoramento ativado
- [ ] Backups testados
- [ ] Logs configurados

---

## 🚀 Scripts de Deployment

### Deploy Inicial

```bash
#!/bin/bash
set -e

echo "🚀 Iniciando deployment do Exclusiva SaaS..."

# Variáveis
REPO_URL="https://github.com/marcuslimadev/exclusiva.git"
DEPLOY_DIR="/var/www/exclusiva"
BACKEND_DIR="$DEPLOY_DIR/backend"

# Clonar repositório
echo "📥 Clonando repositório..."
git clone $REPO_URL $DEPLOY_DIR

# Instalar dependências
echo "📦 Instalando dependências..."
cd $BACKEND_DIR
composer install --optimize-autoloader --no-dev

# Configurar permissões
echo "🔐 Configurando permissões..."
sudo chown -R www-data:www-data $DEPLOY_DIR
sudo chmod -R 755 $DEPLOY_DIR
sudo chmod -R 775 $DEPLOY_DIR/storage
sudo chmod -R 775 $DEPLOY_DIR/bootstrap/cache

# Configurar .env
echo "⚙️  Configurando variáveis de ambiente..."
cp .env.example .env
# Editar .env manualmente ou via script

# Gerar chave
php artisan key:generate

# Executar migrations
echo "🗄️  Executando migrations..."
php artisan migrate --force

# Cache
echo "💾 Gerando cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar serviços
echo "🔄 Reiniciando serviços..."
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx

echo "✅ Deployment concluído com sucesso!"
```

### Deploy de Atualização

```bash
#!/bin/bash
set -e

echo "🔄 Atualizando Exclusiva SaaS..."

DEPLOY_DIR="/var/www/exclusiva"
BACKEND_DIR="$DEPLOY_DIR/backend"

cd $BACKEND_DIR

# Fazer backup
echo "💾 Fazendo backup..."
git stash

# Atualizar código
echo "📥 Atualizando código..."
git pull origin main

# Instalar dependências
echo "📦 Instalando dependências..."
composer install --optimize-autoloader --no-dev

# Executar migrations
echo "🗄️  Executando migrations..."
php artisan migrate --force

# Cache
echo "💾 Gerando cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Limpar cache
php artisan cache:clear
php artisan view:clear

# Reiniciar serviços
echo "🔄 Reiniciando serviços..."
sudo systemctl restart php8.1-fpm
sudo systemctl restart nginx

# Invalidar CloudFront
echo "🚀 Invalidando cache CloudFront..."
aws cloudfront create-invalidation \
    --distribution-id <DISTRIBUTION_ID> \
    --paths "/*"

echo "✅ Atualização concluída com sucesso!"
```

---

## 📚 Documentação

- ✅ Análise do projeto: `/home/ubuntu/analise_projeto_exclusiva.md`
- ✅ Arquitetura SaaS: `/home/ubuntu/exclusiva_saas_architecture.md`
- ✅ Fase 2 (Multi-tenant): `/home/ubuntu/FASE2_MULTI_TENANT_IMPLEMENTATION.md`
- ✅ Fase 3 (Super Admin): `/home/ubuntu/FASE3_SUPER_ADMIN_PANEL.md`
- ✅ Fase 4 (Pagar.me): `/home/ubuntu/FASE4_PAGAR_ME_INTEGRATION.md`
- ✅ Fase 5 (Domínios e Temas): `/home/ubuntu/FASE5_DOMAINS_AND_THEMES.md`
- ✅ Fase 6 (Portal Cliente): `/home/ubuntu/FASE6_CLIENT_PORTAL.md`
- ✅ Fase 7 (este documento): `/home/ubuntu/FASE7_AWS_INFRASTRUCTURE.md`

---

**Data:** 2025-12-18
**Status:** ✅ Completo
**Próximo Passo:** Fase 8 - Testes Finais e Entrega
