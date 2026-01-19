param(
    [switch]$Force,
    [string]$ClusterName = "exclusiva-cluster",
    [string]$ServiceName = "exclusiva-service",
    [string]$TaskFamilyName = "exclusiva-task",
    [string]$AlbName = "exclusiva-alb",
    [string]$TargetGroupName = "exclusiva-tg",
    [string]$RoleName = "ecsTaskExecutionRole",
    [string]$LogGroupName = "/ecs/exclusiva-task",
    [string]$Ec2NamePattern = "exclusiva*"
)

function Confirm-OrAbort($message) {
    if ($Force) {
        return
    }

    Write-Host "$message [y/N]" -ForegroundColor Yellow
    $response = Read-Host
    if ($response -notin @("y", "Y", "yes", "YES")) {
        Write-Host "Operação cancelada." -ForegroundColor Red
        exit 1
    }
}

Write-Host "Parando todos os recursos AWS relacionados ao projeto..." -ForegroundColor Cyan
Confirm-OrAbort "Confirma remover recursos de teste e parar tudo na AWS?"

# ECS: forçar service zero, parar tasks e remover do cluster
$clusterArn = aws ecs describe-clusters --clusters $ClusterName --query 'clusters[0].clusterArn' --output text 2>$null
if ($clusterArn -and $clusterArn -ne "None") {
    Write-Host "Atualizando service ECS para desired count 0..." -ForegroundColor Green
    aws ecs update-service --cluster $ClusterName --service $ServiceName --desired-count 0 --force >/dev/null

    Write-Host "Parando tasks ECS em execução..." -ForegroundColor Green
    $taskArns = aws ecs list-tasks --cluster $ClusterName --query 'taskArns[]' --output text
    if ($taskArns) {
        foreach ($task in $taskArns.Split()) {
            aws ecs stop-task --cluster $ClusterName --task $task --reason "Cleanup script"
        }
    }

    Write-Host "Deletando o service ECS..." -ForegroundColor Green
    aws ecs delete-service --cluster $ClusterName --service $ServiceName --force >/dev/null
} else {
    Write-Host "Cluster ECS '$ClusterName' não encontrado." -ForegroundColor DarkYellow
}

# ALB e target group
$targetGroupArn = aws elbv2 describe-target-groups --names $TargetGroupName --query 'TargetGroups[0].TargetGroupArn' --output text 2>$null
if ($targetGroupArn -and $targetGroupArn -ne "None") {
    Write-Host "Deletando target group $TargetGroupName..." -ForegroundColor Green
    aws elbv2 delete-target-group --target-group-arn $targetGroupArn >/dev/null
} else {
    Write-Host "Target group '$TargetGroupName' não encontrado." -ForegroundColor DarkYellow
}

$albArn = aws elbv2 describe-load-balancers --names $AlbName --query 'LoadBalancers[0].LoadBalancerArn' --output text 2>$null
if ($albArn -and $albArn -ne "None") {
    Write-Host "Deletando listeners do ALB..." -ForegroundColor Green
    $listeners = aws elbv2 describe-listeners --load-balancer-arn $albArn --query 'Listeners[].ListenerArn' --output text
    if ($listeners) {
        foreach ($listener in $listeners.Split()) {
            aws elbv2 delete-listener --listener-arn $listener >/dev/null
        }
    }

    Write-Host "Deletando Application Load Balancer $AlbName..." -ForegroundColor Green
    aws elbv2 delete-load-balancer --load-balancer-arn $albArn >/dev/null
} else {
    Write-Host "Load Balancer '$AlbName' não encontrado." -ForegroundColor DarkYellow
}

# ECS task definition deregistration
$taskRevisions = aws ecs list-task-definitions --family-prefix $TaskFamilyName --sort DESC --query 'taskDefinitionArns[]' --output text
if ($taskRevisions) {
    foreach ($taskArn in $taskRevisions.Split()) {
        Write-Host "Deregistering task definition $taskArn..." -ForegroundColor Green
        aws ecs deregister-task-definition --task-definition $taskArn >/dev/null
    }
}

# Cluster removal
if ($clusterArn -and $clusterArn -ne "None") {
    Write-Host "Excluindo cluster ECS..." -ForegroundColor Green
    aws ecs delete-cluster --cluster $ClusterName >/dev/null
}

# IAM role cleanup
$roleArn = aws iam get-role --role-name $RoleName --query 'Role.Arn' --output text 2>$null
if ($roleArn -and $roleArn -ne "None") {
    Write-Host "Desanexando políticas da role ECS..." -ForegroundColor Green
    aws iam detach-role-policy --role-name $RoleName --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy >/dev/null

    Write-Host "Deletando role IAM $RoleName..." -ForegroundColor Green
    aws iam delete-role --role-name $RoleName >/dev/null
} else {
    Write-Host "Role IAM '$RoleName' não encontrada." -ForegroundColor DarkYellow
}

# CloudWatch Logs
$groupExists = aws logs describe-log-groups --log-group-name-prefix $LogGroupName --query 'logGroups[0].logGroupName' --output text 2>$null
if ($groupExists -and $groupExists -ne "None") {
    Write-Host "Deletando log group $LogGroupName..." -ForegroundColor Green
    aws logs delete-log-group --log-group-name $LogGroupName >/dev/null
}

# EC2 instances tagged with 'exclusiva*'
$ec2Instances = aws ec2 describe-instances --filters @{Name='tag:Name';Values=$Ec2NamePattern},@{Name='instance-state-name';Values=@('pending','running','stopping','stopped')} --query 'Reservations[].Instances[].InstanceId' --output text
if ($ec2Instances) {
    Write-Host "Parando instâncias EC2 taggeadas como '$Ec2NamePattern'..." -ForegroundColor Green
    aws ec2 stop-instances --instance-ids $ec2Instances >/dev/null
}

Write-Host "`nTodas as ações solicitadas foram executadas; verifique o console AWS para confirmar os recursos restantes." -ForegroundColor Cyan
