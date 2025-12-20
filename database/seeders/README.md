# 🌱 Seeders - Imobiliária Exclusiva

Este diretório contém os seeders para popular o banco de dados com dados iniciais da **Imobiliária Exclusiva**.

## 📋 O que é criado

### 🏢 Tenant Exclusiva
- **Nome**: Exclusiva Imóveis
- **Domain**: exclusiva.localhost
- **Plano**: Premium (ativo por 1 ano)
- **Features**: CRM, WhatsApp, Portal, Analytics
- **Integrações**: APM Imóveis, NECA

### 👥 Usuários Criados

| Nome | Email | Senha | Role | Descrição |
|------|--------|-------|------|-----------|
| Super Administrador | admin@exclusiva.com | `password` | super_admin | Acesso total ao sistema |
| Contato Exclusiva | contato@exclusiva.com.br | `Teste@123` | admin | Admin da imobiliária |
| Alexsandra Silva | alexsandra@exclusiva.com.br | `Senha@123` | admin | Administradora |
| Marcus Lima | marcus@exclusiva.com.br | `Dev@123` | admin | Desenvolvedor |
| Corretor Demo | corretor@exclusiva.com.br | `Corretor@123` | agent | Corretor de imóveis |

## 🚀 Como usar

### Opção 1: Scripts automatizados
```bash
# Windows
.\SEED.bat

# Linux/Mac
./seed.sh
```

### Opção 2: Executar diretamente
```bash
php database/seeders/DatabaseSeeder.php
```

### Opção 3: Executar seeder específico
```bash
php database/seeders/ExclusivaSeeder.php
```

## ⚙️ Pré-requisitos

1. **MySQL rodando** (XAMPP ou standalone)
2. **Banco `exclusiva` criado**
3. **Arquivo .env configurado** com dados do banco
4. **Composer install** executado

## 🎯 Após executar os seeders

1. **Iniciar servidor**:
   ```bash
   .\START.bat
   # ou
   php -S 127.0.0.1:8000 -t public
   ```

2. **Acessar sistema**: http://127.0.0.1:8000/app/

3. **Fazer login** com qualquer uma das credenciais criadas

## 📁 Estrutura dos Seeders

```
database/seeders/
├── DatabaseSeeder.php      # Script principal
├── ExclusivaSeeder.php     # Dados da Exclusiva
└── README.md              # Este arquivo
```

## 🛠️ Personalização

Para adicionar novos dados ao seed:

1. **Editar ExclusivaSeeder.php**:
   - Adicionar novos usuários no array `$exclusivaUsers`
   - Modificar dados do tenant em `$exclusivaTenantData`

2. **Criar novo seeder**:
   - Criar arquivo `NovoSeeder.php`
   - Adicionar ao array `$seeders` em `DatabaseSeeder.php`

## 🔍 Verificação

Após executar, você pode verificar se os dados foram criados:

```sql
-- Verificar tenant
SELECT * FROM tenants WHERE slug = 'exclusiva';

-- Verificar usuários
SELECT name, email, role FROM users;

-- Verificar super admin
SELECT * FROM users WHERE role = 'super_admin';
```

## 🚨 Importante

- **Senhas são hasheadas** automaticamente
- **Tenant ID** é atribuído automaticamente aos usuários
- **API Token** é gerado automaticamente para o tenant
- **Dados não são duplicados** (verifica existência antes de criar)

## 📞 Suporte

Para problemas com seeders:
1. Verificar se MySQL está rodando
2. Conferir credenciais no .env
3. Verificar se o banco `exclusiva` existe
4. Ver logs de erro no console