# INSTRUÇÕES: Criar Pessoa do Roberto Jr

## Problema
Lead "Roberto Jr" (telefone: +5531971809143) não tem pessoa associada.

## Solução
Execute o comando artisan para sincronizar todos os leads sem pessoa.

## Como Executar

### Opção 1: Via Terminal SSH (Hostinger Panel)
1. Acesse o painel da Hostinger
2. Vá em "Advanced" > "SSH Access" ou "Terminal"
3. Cole e execute o comando abaixo:

```bash
cd ~/domains/lojadaesquina.store/public_html
/opt/alt/php83/usr/bin/php artisan leads:sync-pessoas
```

### Opção 2: Para um lead específico
Se quiser sincronizar apenas o Roberto Jr, descubra o ID dele primeiro:

```bash
cd ~/domains/lojadaesquina.store/public_html
/opt/alt/php83/usr/bin/php artisan tinker
```

Depois no tinker:
```php
$lead = Lead::where('telefone', 'LIKE', '%5531971809143%')->first();
echo $lead->id;
exit
```

Com o ID, execute:
```bash
/opt/alt/php83/usr/bin/php artisan leads:sync-pessoas --lead-id=ID_DO_LEAD
```

## O que o comando faz
- Busca todos os leads sem pessoa_id associada
- Para cada lead, verifica se já existe uma pessoa com mesmo telefone/email/CPF
- Se encontrar, associa o lead à pessoa existente
- Se não encontrar, cria uma nova pessoa e associa ao lead
- Atualiza o campo `pessoa_id` do lead

## Resultado Esperado
Após executar, o lead Roberto Jr terá:
- Uma pessoa criada ou associada
- Campo `pessoa_id` preenchido
- Perfil da pessoa acessível via interface

## Verificar
Após executar, acesse:
https://lojadaesquina.store/pessoas

E procure por "Roberto Jr" - o cadastro dele deve aparecer.
