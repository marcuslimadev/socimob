# Pendencias - Tenant e Publicacao

Este guia resume o que ainda falta para concluir o ajuste de tenant,
commitar e publicar.

## Pendencias

- Confirmar o fluxo no painel administrativo salvando nome, logo, razao social,
  CNPJ, endereco, email e telefone e verificar se persistem apos reload.
- Validar o carregamento do site publico pelo dominio do tenant e confirmar
  que os dados de branding aparecem (nome, logo, cores e contatos).
- Executar o commit e push das alteracoes locais.
- Publicar no servidor via SSH.

## Como validar localmente

1) Abrir o painel administrativo e salvar os dados da empresa.
2) Recarregar a pagina e confirmar se os dados persistiram.
3) Abrir o dominio do tenant (ex: https://exclusiva.com) e validar o site.

## Deploy via SSH

```
ssh -p 65002 u815655858@145.223.105.168
cd domains/lojadaesquina.store/public_html/
git pull
```

## Observacoes

- O sistema usa configuracoes do tenant para carregar o site publico.
- Caso o dominio nao resolva para o tenant correto, revisar a configuracao
  de dominio e o middleware resolve-tenant.
