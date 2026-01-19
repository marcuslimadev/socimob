# =====================================
# AWS CLEANUP E CRIAÇÃO ECS - EXCLUSIVA
# =====================================
# ATENÇÃO: Revise cada comando antes de executar!
# Execute linha por linha ou por seção para controlar melhor

Write-Host "=== INICIANDO INVENTÁRIO DOS RECURSOS AWS ===" -ForegroundColor Green

# 1. INVENTÁRIO – confirme os IDs dos recursos
Write-Host "1. Listando instâncias EC2 existentes..." -ForegroundColor Yellow
aws ec2 describe-instances --output table

Write-Host "2. Listando instâncias com tags..." -ForegroundColor Yellow
aws ec2 describe-instances --filters "Name=tag:Name,Values=*" --output table

Write-Host "3. Listando volumes disponíveis (órfãos)..." -ForegroundColor Yellow
aws ec2 describe-volumes --filters Name=status,Values=available --output table

Write-Host "4. Listando clusters ECS..." -ForegroundColor Yellow
aws ecs list-clusters

Write-Host "5. Listando services ECS..." -ForegroundColor Yellow
aws ecs list-services --cluster exclusiva-cluster

Write-Host "6. Listando RDS (exceto imobi-postgres)..." -ForegroundColor Yellow
aws rds describe-db-instances --query "DBInstances[?DBInstanceIdentifier!='imobi-postgres']" --output table

Write-Host "7. Listando Security Groups..." -ForegroundColor Yellow
aws ec2 describe-security-groups --output table

Write-Host "8. Listando Route53 zones..." -ForegroundColor Yellow
aws route53 list-hosted-zones --output table

Write-Host "9. Listando Load Balancers..." -ForegroundColor Yellow
aws elbv2 describe-load-balancers --output table

Write-Host "10. Listando Target Groups..." -ForegroundColor Yellow
aws elbv2 describe-target-groups --output table

Write-Host "`n=== INVENTÁRIO CONCLUÍDO ===" -ForegroundColor Green
Read-Host "Pressione ENTER para continuar com a limpeza ou CTRL+C para cancelar"

# =====================================
# 2. LIMPEZA DE RECURSOS ANTIGOS
# =====================================
Write-Host "`n=== INICIANDO LIMPEZA DE RECURSOS ===" -ForegroundColor Yellow

# ATENÇÃO: Substitua pelos IDs reais que aparecerem no inventário acima
# DESCOMENTE APENAS AS LINHAS DOS RECURSOS QUE VOCÊ CONFIRMAR QUE PODE EXCLUIR

# Parar instâncias EC2 antigas (substitua pelo ID real)
# aws ec2 stop-instances --instance-ids i-XXXXXXXXXXXXXXXXX

# Encerrar instâncias EC2 antigas (substitua pelo ID real)
# aws ec2 terminate-instances --instance-ids i-XXXXXXXXXXXXXXXXX

# Excluir volumes órfãos (substitua pelo ID real)
# aws ec2 delete-volume --volume-id vol-XXXXXXXXXXXXXXXXX

# Parar services ECS antigos
# aws ecs update-service --cluster OLD-CLUSTER --service OLD-SERVICE --desired-count 0
# aws ecs delete-service --cluster OLD-CLUSTER --service OLD-SERVICE --force

# Excluir load balancers antigos (substitua pelo ARN real)
# aws elbv2 delete-load-balancer --load-balancer-arn arn:aws:elasticloadbalancing:sa-east-1:575098225472:loadbalancer/app/OLD-ALB/XXXXXXXXXX

Write-Host "LIMPEZA CONCLUÍDA (ou pulada se comentada)" -ForegroundColor Green
Read-Host "Pressione ENTER para continuar com a criação do ECS"

# =====================================
# 3. CRIAR INFRAESTRUTURA ECS FARGATE
# =====================================
Write-Host "`n=== CRIANDO CLUSTER ECS ===" -ForegroundColor Green

# Criar cluster ECS
Write-Host "Criando cluster exclusiva-cluster..." -ForegroundColor Yellow
aws ecs create-cluster --cluster-name exclusiva-cluster

# Criar role para task execution (se não existir)
Write-Host "Criando role para ECS Task Execution..." -ForegroundColor Yellow
$trustPolicy = @'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ecs-tasks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
'@

$trustPolicy | Out-File -FilePath "trust-policy.json" -Encoding UTF8
aws iam create-role --role-name ecsTaskExecutionRole --assume-role-policy-document file://trust-policy.json
aws iam attach-role-policy --role-name ecsTaskExecutionRole --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy

# Registrar task definition
Write-Host "Registrando task definition..." -ForegroundColor Yellow

$taskDefinition = @'
{
  "family": "exclusiva-task",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "1024",
  "memory": "2048",
  "executionRoleArn": "arn:aws:iam::575098225472:role/ecsTaskExecutionRole",
  "containerDefinitions": [
    {
      "name": "backend",
      "image": "nginx:latest",
      "portMappings": [
        {
          "containerPort": 80,
          "protocol": "tcp"
        }
      ],
      "essential": true,
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/exclusiva-task",
          "awslogs-region": "sa-east-1",
          "awslogs-stream-prefix": "ecs"
        }
      },
      "environment": [
        {
          "name": "DB_CONNECTION",
          "value": "pgsql"
        },
        {
          "name": "DB_HOST",
          "value": "imobi-postgres.czk0ka4g8s8f.sa-east-1.rds.amazonaws.com"
        },
        {
          "name": "DB_DATABASE",
          "value": "imobi"
        },
        {
          "name": "DB_USERNAME",
          "value": "imobi_admin"
        },
        {
          "name": "DB_PASSWORD",
          "value": "exclusiva123456"
        }
      ]
    }
  ]
}
'@

$taskDefinition | Out-File -FilePath "task-definition.json" -Encoding UTF8

# Criar log group
Write-Host "Criando log group..." -ForegroundColor Yellow
aws logs create-log-group --log-group-name "/ecs/exclusiva-task"

# Registrar task definition
aws ecs register-task-definition --cli-input-json file://task-definition.json

# Criar Target Group para ALB
Write-Host "Criando Target Group..." -ForegroundColor Yellow
$targetGroupArn = aws elbv2 create-target-group `
  --name exclusiva-tg `
  --protocol HTTP `
  --port 80 `
  --vpc-id vpc-0042bff6233 `
  --target-type ip `
  --health-check-path "/" `
  --health-check-protocol HTTP `
  --health-check-interval-seconds 30 `
  --health-check-timeout-seconds 5 `
  --healthy-threshold-count 2 `
  --unhealthy-threshold-count 3 `
  --query 'TargetGroups[0].TargetGroupArn' --output text

Write-Host "Target Group ARN: $targetGroupArn" -ForegroundColor Green

# Criar Application Load Balancer
Write-Host "Criando Application Load Balancer..." -ForegroundColor Yellow
$albArn = aws elbv2 create-load-balancer `
  --name exclusiva-alb `
  --subnets subnet-09996da9ce9a08f16 subnet-05b32a2ccfcba6c5c subnet-07834e01856131bfc `
  --security-groups sg-06c2b25529eb0517f `
  --scheme internet-facing `
  --type application `
  --ip-address-type ipv4 `
  --query 'LoadBalancers[0].LoadBalancerArn' --output text

Write-Host "ALB ARN: $albArn" -ForegroundColor Green

# Criar listener para ALB
Write-Host "Criando listener para ALB..." -ForegroundColor Yellow
aws elbv2 create-listener `
  --load-balancer-arn $albArn `
  --protocol HTTP `
  --port 80 `
  --default-actions Type=forward,TargetGroupArn=$targetGroupArn

# Criar service ECS
Write-Host "Criando service ECS..." -ForegroundColor Yellow
aws ecs create-service `
  --cluster exclusiva-cluster `
  --service-name exclusiva-service `
  --task-definition exclusiva-task `
  --launch-type FARGATE `
  --desired-count 1 `
  --network-configuration "awsvpcConfiguration={subnets=[subnet-09996da9ce9a08f16,subnet-05b32a2ccfcba6c5c,subnet-07834e01856131bfc],securityGroups=[sg-06c2b25529eb0517f],assignPublicIp=ENABLED}" `
  --load-balancers "targetGroupArn=$targetGroupArn,containerName=backend,containerPort=80"

# Obter DNS do ALB
Write-Host "Obtendo DNS do Load Balancer..." -ForegroundColor Yellow
$albDns = aws elbv2 describe-load-balancers --load-balancer-arns $albArn --query 'LoadBalancers[0].DNSName' --output text
Write-Host "ALB DNS: $albDns" -ForegroundColor Green

Write-Host "`n=== CRIAÇÃO CONCLUÍDA ===" -ForegroundColor Green
Write-Host "Cluster ECS: exclusiva-cluster" -ForegroundColor Cyan
Write-Host "Service: exclusiva-service" -ForegroundColor Cyan
Write-Host "Load Balancer DNS: $albDns" -ForegroundColor Cyan
Write-Host "Target Group: $targetGroupArn" -ForegroundColor Cyan

Write-Host "`nPróximos passos:" -ForegroundColor Yellow
Write-Host "1. Aguardar o service ficar RUNNING (~5 minutos)" -ForegroundColor White
Write-Host "2. Testar acesso via $albDns" -ForegroundColor White
Write-Host "3. Configurar Route53 para apontar para o ALB" -ForegroundColor White
Write-Host "4. Atualizar task definition com sua imagem Docker" -ForegroundColor White

# Limpar arquivos temporários
Remove-Item "trust-policy.json" -ErrorAction SilentlyContinue
Remove-Item "task-definition.json" -ErrorAction SilentlyContinue

Write-Host "`nScript concluído! Monitore os resources no console AWS." -ForegroundColor Green