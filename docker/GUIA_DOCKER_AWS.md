# 🐳 Guia de Docker e AWS - Exclusiva SaaS

## Parte 1: Docker Local

### ✅ Você Precisa de Docker?

**SIM, você precisa de Docker se:**
- Quer ambiente consistente entre local e produção
- Quer facilitar o deployment
- Quer evitar conflitos de versões
- Quer escalar facilmente

**NÃO precisa de Docker se:**
- Quer rodar direto na máquina local
- Quer mais controle manual
- Quer debugging mais direto

### 🚀 Executar com Docker Localmente

#### 1. Instalar Docker
```bash
# macOS
brew install docker docker-compose

# Ubuntu
sudo apt-get install docker.io docker-compose

# Windows
# Baixar Docker Desktop em https://www.docker.com/products/docker-desktop
```

#### 2. Preparar Arquivos
```bash
# Copiar .env.example
cp docker/.env.example .env

# Editar .env com suas variáveis
nano .env
```

#### 3. Executar com Docker Compose
```bash
# Iniciar containers
docker-compose -f docker/docker-compose.yml up -d

# Verificar status
docker-compose -f docker/docker-compose.yml ps

# Ver logs
docker-compose -f docker/docker-compose.yml logs -f app
```

#### 4. Testar Localmente
```bash
# Acessar aplicação
curl http://localhost

# Acessar API
curl http://localhost/api/theme

# Acessar banco de dados
docker-compose -f docker/docker-compose.yml exec db mysql -u exclusiva -p
```

#### 5. Parar Containers
```bash
docker-compose -f docker/docker-compose.yml down

# Remover volumes (cuidado!)
docker-compose -f docker/docker-compose.yml down -v
```

---

## Parte 2: Docker na AWS

### ✅ Opções de Deploy na AWS

#### Opção 1: EC2 + Docker (Recomendado)
- **Vantagem:** Controle total, menor custo
- **Desvantagem:** Gerenciar infraestrutura
- **Custo:** ~$20-50/mês

#### Opção 2: ECS (Elastic Container Service)
- **Vantagem:** Gerenciado pela AWS, escalável
- **Desvantagem:** Mais caro, mais complexo
- **Custo:** ~$50-200/mês

#### Opção 3: ECS Fargate
- **Vantagem:** Sem gerenciar servidores
- **Desvantagem:** Mais caro
- **Custo:** ~$100-300/mês

#### Opção 4: App Runner
- **Vantagem:** Mais simples que ECS
- **Desvantagem:** Menos controle
- **Custo:** ~$50-150/mês

### 🎯 Recomendação

**Para começar:** EC2 + Docker (Opção 1)
- Menor custo
- Controle total
- Fácil de gerenciar
- Escalável

---

## 🚀 Deploy na AWS com EC2 + Docker

### Passo 1: Criar EC2 Instância

```bash
# Via AWS CLI
aws ec2 run-instances \
    --image-id ami-0c55b159cbfafe1f0 \
    --instance-type t3.large \
    --key-name sua-chave \
    --security-groups exclusiva-sg \
    --region us-east-1
```

### Passo 2: Conectar na Instância

```bash
# Obter IP público
aws ec2 describe-instances --query 'Reservations[0].Instances[0].PublicIpAddress'

# Conectar
ssh -i sua-chave.pem ubuntu@<IP_PUBLICO>
```

### Passo 3: Instalar Docker

```bash
# Atualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Instalar Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Adicionar usuário ao grupo docker
sudo usermod -aG docker $USER
newgrp docker
```

### Passo 4: Clonar Repositório

```bash
# Clonar código
git clone https://github.com/marcuslimadev/exclusiva.git
cd exclusiva

# Copiar .env
cp docker/.env.example .env

# Editar .env com variáveis de produção
nano .env
```

### Passo 5: Configurar RDS (Banco de Dados)

```bash
# Criar banco de dados RDS
aws rds create-db-instance \
    --db-instance-identifier exclusiva-db \
    --db-instance-class db.t3.micro \
    --engine mysql \
    --master-username admin \
    --master-user-password sua_senha_segura \
    --allocated-storage 100 \
    --storage-type gp3

# Obter endpoint
aws rds describe-db-instances --query 'DBInstances[0].Endpoint.Address'

# Atualizar .env com endpoint
# DB_HOST=seu-endpoint.rds.amazonaws.com
```

### Passo 6: Construir e Executar Docker

```bash
# Construir imagem
docker build -t exclusiva-saas:latest -f docker/Dockerfile .

# Executar container
docker-compose -f docker/docker-compose.yml up -d

# Verificar logs
docker-compose -f docker/docker-compose.yml logs -f app

# Verificar status
docker-compose -f docker/docker-compose.yml ps
```

### Passo 7: Configurar Nginx Reverse Proxy (Opcional)

```bash
# Instalar Nginx
sudo apt install -y nginx

# Criar configuração
sudo nano /etc/nginx/sites-available/exclusiva

# Conteúdo:
server {
    listen 80;
    server_name exclusiva.com.br;

    location / {
        proxy_pass http://localhost:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# Ativar site
sudo ln -s /etc/nginx/sites-available/exclusiva /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Passo 8: Configurar SSL (Let's Encrypt)

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Gerar certificado
sudo certbot certonly --nginx -d exclusiva.com.br

# Atualizar Nginx com SSL
# (Certbot faz isso automaticamente)

# Renovação automática
sudo systemctl enable certbot.timer
```

### Passo 9: Configurar Route 53

```bash
# Criar hosted zone
aws route53 create-hosted-zone \
    --name exclusiva.com.br \
    --caller-reference $(date +%s)

# Criar registro A
aws route53 change-resource-record-sets \
    --hosted-zone-id <ZONE_ID> \
    --change-batch '{
        "Changes": [{
            "Action": "CREATE",
            "ResourceRecordSet": {
                "Name": "exclusiva.com.br",
                "Type": "A",
                "TTL": 300,
                "ResourceRecords": [{"Value": "<IP_PUBLICO>"}]
            }
        }]
    }'
```

### Passo 10: Configurar CloudFront (CDN)

```bash
# Criar distribuição CloudFront
aws cloudfront create-distribution \
    --distribution-config file://cloudfront-config.json
```

---

## 📊 Comparação: Docker vs Sem Docker

| Aspecto | Com Docker | Sem Docker |
|---------|-----------|-----------|
| **Setup** | Mais rápido | Mais lento |
| **Consistência** | Garantida | Pode variar |
| **Escalabilidade** | Fácil | Difícil |
| **Custo** | Mesmo | Mesmo |
| **Complexidade** | Média | Baixa |
| **Manutenção** | Mais fácil | Mais difícil |

---

## ✅ Checklist de Deploy

### Local
- [ ] Docker instalado
- [ ] Docker Compose instalado
- [ ] .env configurado
- [ ] Containers rodando
- [ ] Banco de dados acessível
- [ ] API respondendo
- [ ] Testes passando

### AWS
- [ ] EC2 instância criada
- [ ] Docker instalado
- [ ] RDS banco de dados criado
- [ ] Código clonado
- [ ] .env configurado
- [ ] Docker containers rodando
- [ ] Nginx configurado
- [ ] SSL configurado
- [ ] Route 53 DNS configurado
- [ ] CloudFront distribuição criada
- [ ] Monitoramento ativado

---

## 🔍 Monitoramento

### Verificar Saúde dos Containers
```bash
docker-compose -f docker/docker-compose.yml ps

# Verificar logs
docker-compose -f docker/docker-compose.yml logs app
docker-compose -f docker/docker-compose.yml logs db
docker-compose -f docker/docker-compose.yml logs redis
```

### Verificar Saúde da Aplicação
```bash
# Health check
curl http://localhost/health

# API status
curl http://localhost/api/theme
```

### CloudWatch (AWS)
```bash
# Ver logs
aws logs tail /aws/ec2/exclusiva --follow

# Criar alarme
aws cloudwatch put-metric-alarm \
    --alarm-name exclusiva-cpu-high \
    --alarm-description "CPU above 80%" \
    --metric-name CPUUtilization \
    --namespace AWS/EC2 \
    --statistic Average \
    --period 300 \
    --threshold 80 \
    --comparison-operator GreaterThanThreshold
```

---

## 🚨 Troubleshooting

### Container não inicia
```bash
# Ver logs
docker-compose -f docker/docker-compose.yml logs app

# Verificar permissões
docker-compose -f docker/docker-compose.yml exec app ls -la /var/www/exclusiva
```

### Banco de dados não conecta
```bash
# Verificar status do container
docker-compose -f docker/docker-compose.yml ps db

# Testar conexão
docker-compose -f docker/docker-compose.yml exec db mysql -u exclusiva -p
```

### Porta já em uso
```bash
# Encontrar processo usando porta 80
sudo lsof -i :80

# Matar processo
sudo kill -9 <PID>

# Ou mudar porta em docker-compose.yml
# ports:
#   - "8080:80"
```

---

## 📚 Recursos Adicionais

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [AWS EC2 Documentation](https://docs.aws.amazon.com/ec2/)
- [AWS RDS Documentation](https://docs.aws.amazon.com/rds/)
- [AWS CloudFront Documentation](https://docs.aws.amazon.com/cloudfront/)

---

**Data:** 2025-12-18
**Versão:** 1.0.0
