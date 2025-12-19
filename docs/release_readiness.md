# Readiness notes for imobiliária SaaS

## O que encontramos
- Controladores de leads e conversas acessavam tabelas diretamente sem respeitar o `tenant_id`, permitindo vazamento de dados entre empresas.
- Diversos modelos e tabelas referenciados pelo código (leads avançados, mensagens, documentos, matches, atividades, imóveis detalhados) não existiam, quebrando fluxos essenciais como atendimento, envio/recebimento de mensagens e associação de imóveis.
- Tabela de imóveis tinha apenas campos mínimos, inviabilizando sincronização com a API e pré-visualizações de propriedades.

## Correções aplicadas
- **Isolamento multi-tenant:** filtros explícitos de `tenant_id` nas consultas diretas de leads e conversas, além de escopo automático nas novas entidades.
- **Modelos Eloquent recriados:** Lead, Conversa, Mensagem, LeadDocument, LeadPropertyMatch, Atividade e Property com relacionamentos necessários.
- **Migrações novas:**
  - Complemento de campos de leads e conversas (status flexível, corretor, datas de interação, diagnóstico, etc.).
  - Estrutura completa para mensagens, documentos, matches e atividades.
  - Ampliação da tabela de imóveis para suportar todos os campos usados em sincronização, IA e portal público, incluindo `tenant_id`.

## Próximos passos recomendados
- Rodar `php artisan migrate` no backend para materializar as tabelas/campos adicionados.
- Validar autenticação social (Google) e flows de atendimento no frontend após as migrações.
- Revisar seeds e factories para incluir `tenant_id` onde necessário.
