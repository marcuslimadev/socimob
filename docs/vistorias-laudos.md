# Módulo de Vistorias e Laudos

## Fluxo principal

1. Crie a vistoria em `POST /api/vistorias` com `imovel_id` ou `contrato_id`, `tipo`, vistoriador/responsável e prazo de contestação.
2. Inicie em `POST /api/vistorias/{id}/iniciar`.
3. Cadastre ambientes, itens, chaves, inconformidades e mídias pelos endpoints de `/api/vistorias/{id}/...`.
4. Finalize em `POST /api/vistorias/{id}/finalizar`; o sistema define a data limite de contestação quando ela ainda não foi informada.
5. Gere o PDF em `POST /api/vistorias/{id}/gerar-pdf` e baixe em `GET /api/vistorias/{id}/download-pdf`.

## Links públicos

- Mídias: `/vistorias/publico/{link_publico_midias_token}/midias`
- Contestação: `/vistorias/publico/{link_contestacao_token}/contestacao`
- PDF público: `/vistorias/publico/{link_publico_midias_token}/pdf`

Os tokens são longos e não expõem IDs internos. Vistorias canceladas bloqueiam acesso público.

## Storage

As mídias novas ficam em:

`storage/app/public/tenants/{tenant_id}/vistorias/{vistoria_id}/midias/`

PDFs ficam em:

`storage/app/public/tenants/{tenant_id}/vistorias/{vistoria_id}/pdf/`

## PDF

O template fica em `resources/views/pdfs/vistorias/termo.blade.php` e inclui dados da imobiliária, imóvel, partes, critérios, chaves, ambientes, inconformidades, mídias, termos, assinaturas e QR Codes para mídias e contestação.

## Tela mobile

A rota frontend `/vistorias/:id/execucao` agora permite iniciar, cadastrar ambientes, itens, inconformidades, anexar fotos/vídeos por ambiente e finalizar a vistoria.
