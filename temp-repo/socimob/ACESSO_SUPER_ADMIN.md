# Acesso Super Admin - SOCIMOB SaaS

## Credenciais Super Admin

- **Email:** admin@exclusiva.com
- **Senha:** password
- **Role:** super_admin

## Como Fazer Login

### Via PowerShell (Windows)

```powershell
$body = @{email='admin@exclusiva.com';password='password'} | ConvertTo-Json
$response = Invoke-RestMethod -Uri 'http://localhost:8080/api/auth/login' -Method POST -Body $body -ContentType 'application/json'
$response
```

### Via cURL (Linux/Mac)

```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@exclusiva.com","password":"password"}'
```

## Resposta de Login

```json
{
  "success": true,
  "token": "MXwxNzY2MDc3MTg4fGJhc2U2NDpNamxEYVRGWFRFWjZXRFpyVlhCSlFrMDFiRTlXTUROUVUzSklZMmRFVGpRPQ==",
  "user": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@exclusiva.com",
    "role": "super_admin",
    "tipo": "Super Admin"
  },
  "message": "Login realizado com sucesso!"
}
```

## Como Usar o Token

Após fazer login, você receberá um `token`. Use este token em todas as requisições autenticadas:

### PowerShell

```powershell
$token = $response.token
$headers = @{Authorization="Bearer $token"}

# Exemplo de requisição autenticada
Invoke-RestMethod -Uri 'http://localhost:8080/api/dashboard' -Method GET -Headers $headers
```

### cURL

```bash
TOKEN="seu_token_aqui"
curl -X GET http://localhost:8080/api/dashboard \
  -H "Authorization: Bearer $TOKEN"
```

## Rotas Disponíveis

### Autenticação
- `POST /api/auth/login` - Fazer login
- `POST /api/auth/logout` - Fazer logout
- `POST /api/auth/refresh` - Renovar token
- `GET /api/auth/me` - Ver informações do usuário logado

### Super Admin (requer role: super_admin)
- Rotas definidas em `routes/super-admin.php`
- Acesso total ao sistema
- Gerenciamento de todas as imobiliárias (tenants)

### Admin (requer role: admin)
- Rotas definidas em `routes/admin.php`
- Gerenciamento da imobiliária específica

### Client Portal (requer role: client)
- Rotas definidas em `routes/client-portal.php`
- Acesso limitado aos clientes

## Estrutura de Usuários

O sistema tem 4 níveis de acesso:

1. **super_admin** - Acesso total, gerencia todas as imobiliárias
2. **admin** - Gerencia uma imobiliária específica
3. **user** - Funcionário da imobiliária
4. **client** - Cliente final

## Alterando a Senha

Para alterar a senha do super admin, execute no banco de dados:

```sql
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
WHERE email = 'admin@exclusiva.com';
```

Ou use o hash gerado pelo Laravel:

```php
php artisan tinker
>>> bcrypt('sua_nova_senha')
```

## Conectando ao Banco de Dados

```bash
mysql -h 127.0.0.1 -P 3307 -u exclusiva -p
# Senha: sua_senha_segura_aqui
```

## Status dos Serviços

Verificar containers rodando:
```bash
docker ps
```

Verificar logs:
```bash
docker logs exclusiva-app
docker logs exclusiva-db
```

## Próximos Passos

1. Implementar autenticação JWT completa
2. Criar endpoints de gerenciamento de tenants
3. Implementar middleware de autorização por role
4. Configurar CORS para frontend
5. Implementar refresh token
