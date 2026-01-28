# Documento de Desenvolvimento - Sistema de Gestão Imobiliária (Pleno)

**Autor:** Manus AI
**Data:** 28 de Novembro de 2025
**Base de Análise:** Portal Exclusiva Lar Imóveis (Pleno)

## 1. Introdução

Este documento detalha as funcionalidades e a estrutura do sistema de gestão imobiliária analisado, com o objetivo de fornecer uma base completa para o desenvolvimento de um sistema com funcionalidades equivalentes. O sistema é modular, focado em processos de **Vistoria** e **Assinatura Eletrônica**, com módulos de suporte para **Pessoas** e **Imóveis**.

## 2. Requisitos Funcionais (RF)

Os requisitos funcionais são agrupados por módulo, refletindo a estrutura observada no sistema.

### 2.1. Módulo de Acesso e Autenticação

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-001 | **Login** | O sistema deve permitir o login do usuário através de e-mail e senha. |
| RF-002 | **Recuperação de Senha** | O sistema deve oferecer um mecanismo para recuperação de senha (e.g., "Esqueceu sua senha?"). |
| RF-003 | **Dashboard/Acessos Rápidos** | Após o login, o usuário deve ser direcionado a um painel com acessos rápidos aos módulos principais (Vistoria, Assinatura, Locação, Financeiro, Vendas). |
| RF-004 | **Notificações** | O sistema deve exibir um contador de notificações não lidas. |
| RF-005 | **Gestão de Perfil** | O sistema deve permitir ao usuário acessar e gerenciar seu perfil. |

### 2.2. Módulo de Vistoria (Inspeção)

Este módulo é o mais complexo e central para a gestão de inspeções de imóveis.

#### 2.2.1. Gestão de Vistorias

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-100 | **Listagem de Vistorias** | Exibir uma lista paginada e pesquisável de todas as vistorias. |
| RF-101 | **Filtros de Vistoria** | Permitir a pesquisa por texto livre e a aplicação de filtros avançados (e.g., Código, Status, Cliente, Imóvel, Tipo, Vistoriadores, Pessoas, Metragem, Mobiliado). |
| RF-102 | **Visualização de Detalhes** | Permitir a visualização de todos os detalhes de uma vistoria específica. |
| RF-103 | **Exportação de Dados** | Permitir a exportação da lista de vistorias filtradas. |

#### 2.2.2. Gestão de Solicitações de Vistoria

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-110 | **Criação de Solicitação** | Permitir a criação de uma nova solicitação de vistoria com abas de detalhamento. |
| RF-111 | **Visualização em Kanban** | Exibir as solicitações em um formato Kanban (colunas: Solicitada, Designada, Em Andamento, Concluída, Cancelada). |
| RF-112 | **Visualização em Calendário** | Exibir as solicitações em um formato de calendário com filtro de período. |
| RF-113 | **Visualização em Lista** | Exibir as solicitações em formato de lista com colunas detalhadas (Código, Data Agendamento, Identificação, Status, Imóvel, Tipo, Vistoriadores, Metragem, Mobiliado, Tempo estimado). |
| RF-114 | **Formulário de Solicitação** | O formulário deve conter as seguintes abas: |
| RF-114.1 | **1. Solicitação** | Campos obrigatórios para Cliente e Tipo de Vistoria. |
| RF-114.2 | **2. Observações** | Campo de texto livre para observações. |
| RF-114.3 | **3. Pessoas** | Adicionar e gerenciar pessoas relacionadas à vistoria. |
| RF-114.4 | **5. Histórico** | Exibir o histórico de ações e mudanças na solicitação. |

#### 2.2.3. Gestão de Contestações

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-120 | **Listagem de Contestações** | Exibir uma lista paginada e pesquisável de todas as contestações (divergências). |
| RF-121 | **Filtros de Contestações** | Permitir a pesquisa por período e a visualização em Kanban (Apontadas, Em Análise, Finalizadas) ou Lista. |

### 2.3. Módulo de Pessoas

Este módulo gerencia o cadastro de clientes, proprietários, inquilinos, etc.

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-200 | **Listagem de Pessoas** | Exibir uma lista paginada e pesquisável de pessoas (Nome, CPF/CNPJ, E-mail, Telefone). |
| RF-201 | **Pesquisa de Pessoas** | Permitir a pesquisa por texto livre. |
| RF-202 | **Criação de Pessoa** | Permitir o cadastro de uma nova pessoa com as seguintes abas: |
| RF-202.1 | **Principal** | Campos para Nome, País, Telefone/Celular, E-mail. Seleção de tipo (Física/Jurídica). |
| RF-202.2 | **Pessoa Física** | Campos para CPF, RG, Órgão Expedidor, Data de Expedição, CNH, Data de Nascimento. |
| RF-202.3 | **Endereço** | Campos para CEP, Estado, Cidade, Bairro, Endereço, Número, Complemento. |
| RF-202.4 | **Contatos** | Adicionar múltiplos contatos (Tipo, Contato, Descrição). |

### 2.4. Módulo de Imóveis

Este módulo gerencia o cadastro de imóveis.

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-300 | **Listagem de Imóveis** | Exibir uma lista paginada e pesquisável de imóveis (Código, Identificação, Tipo, Cidade, Endereço, Finalidade). |
| RF-301 | **Pesquisa de Imóveis** | Permitir a pesquisa por texto livre. |
| RF-302 | **Criação de Imóvel** | Permitir o cadastro de um novo imóvel com os seguintes campos: |
| RF-302.1 | **Identificação** | Campo de texto livre para identificação. |
| RF-302.2 | **Tipo do Imóvel** | Campo de seleção obrigatório. |
| RF-302.3 | **Edifício / Condomínio** | Campo de seleção. |
| RF-302.4 | **Endereço** | Campos para CEP, Estado, Cidade, Bairro, Endereço, Número, Complemento. |
| RF-302.5 | **Detalhes** | Campo para Metragem (m²). |

### 2.5. Módulo de Assinatura Eletrônica

Este módulo gerencia o processo de assinatura de documentos.

| RF ID | Funcionalidade | Descrição |
| :---: | :--- | :--- |
| RF-400 | **Listagem de Documentos** | Exibir uma lista paginada e pesquisável de documentos para assinatura (Código, Data, Assunto, Status, Assinantes). |
| RF-401 | **Pesquisa de Documentos** | Permitir a pesquisa por texto livre. |
| RF-402 | **Criação de Documento** | Permitir a criação de um novo documento para assinatura. |
| RF-403 | **Status de Assinatura** | Exibir o status de cada documento (e.g., Assinado, Pendente assinatura). |

## 3. Requisitos Não Funcionais (RNF)

| RNF ID | Categoria | Requisito |
| :---: | :--- | :--- |
| RNF-001 | **Usabilidade** | A interface deve ser intuitiva e responsiva, adaptando-se a diferentes tamanhos de tela (desktop, tablet, mobile). |
| RNF-002 | **Performance** | O tempo de carregamento das listas e formulários não deve exceder 3 segundos. |
| RNF-003 | **Segurança** | O sistema deve utilizar criptografia SSL/TLS. As senhas devem ser armazenadas de forma segura (e.g., *hashing* com *salt*). |
| RNF-004 | **Segurança** | O acesso aos módulos deve ser controlado por permissões de usuário. |
| RNF-005 | **Integração** | O sistema deve integrar-se com um serviço de envio de e-mail para notificações e processos de assinatura. |
| RNF-006 | **Integração** | O sistema deve integrar-se com um serviço de consulta de CEP para preenchimento automático de endereço. |

## 4. Arquitetura Sugerida

Sugere-se uma arquitetura de microsserviços ou uma arquitetura monolítica modular, utilizando tecnologias modernas para garantir escalabilidade e manutenibilidade.

| Componente | Descrição | Tecnologia Sugerida |
| :--- | :--- | :--- |
| **Frontend** | Interface do usuário responsiva e interativa. | Angular, React ou Vue.js (com preferência para Angular, dada a aparência do sistema original). |
| **Backend (API)** | Lógica de negócios, autenticação e comunicação com o banco de dados. | Node.js (Express/NestJS) ou Python (Django/FastAPI). |
| **Banco de Dados** | Armazenamento de dados estruturados. | PostgreSQL (escalabilidade e integridade de dados) ou MySQL. |
| **Serviço de Assinatura** | Serviço dedicado para gestão de documentos e assinaturas eletrônicas. | Implementação própria com criptografia ou integração com um serviço de terceiros (e.g., DocuSign, Clicksign). |
| **Serviço de Vistoria** | Serviço dedicado para gestão de vistorias, incluindo armazenamento de fotos e vídeos. | Implementação própria com armazenamento em nuvem (e.g., AWS S3, Google Cloud Storage). |

## 5. Modelagem de Dados (Entidades Principais)

A modelagem de dados deve refletir as entidades observadas e suas relações.

### 5.1. Entidade `Pessoa`

| Campo | Tipo de Dado | Descrição |
| :--- | :--- | :--- |
| `id` | UUID/INT | Identificador único. |
| `nome` | VARCHAR(255) | Nome completo ou Razão Social. |
| `tipo` | ENUM | 'Física' ou 'Jurídica'. |
| `email` | VARCHAR(255) | E-mail principal. |
| `telefone` | VARCHAR(20) | Telefone/Celular principal. |
| `cpf_cnpj` | VARCHAR(18) | CPF ou CNPJ. |
| `rg` | VARCHAR(20) | RG (se Pessoa Física). |
| `data_nascimento` | DATE | Data de nascimento (se Pessoa Física). |

### 5.2. Entidade `Endereco`

| Campo | Tipo de Dado | Descrição |
| :--- | :--- | :--- |
| `id` | UUID/INT | Identificador único. |
| `pessoa_id` | FK | Chave estrangeira para `Pessoa`. |
| `cep` | VARCHAR(10) | Código de Endereçamento Postal. |
| `estado` | VARCHAR(50) | Estado. |
| `cidade` | VARCHAR(100) | Cidade. |
| `bairro` | VARCHAR(100) | Bairro. |
| `logradouro` | VARCHAR(255) | Endereço (Rua, Avenida, etc.). |
| `numero` | VARCHAR(10) | Número. |
| `complemento` | VARCHAR(100) | Complemento. |

### 5.3. Entidade `Imovel`

| Campo | Tipo de Dado | Descrição |
| :--- | :--- | :--- |
| `id` | UUID/INT | Identificador único. |
| `codigo` | VARCHAR(50) | Código interno do imóvel. |
| `identificacao` | VARCHAR(255) | Nome ou identificação do imóvel. |
| `tipo` | VARCHAR(50) | Tipo do imóvel (e.g., Casa, Apartamento, Loja). |
| `finalidade` | ENUM | 'Residencial' ou 'Comercial'. |
| `metragem` | DECIMAL(10, 2) | Metragem em m². |
| `edificio_condominio` | VARCHAR(255) | Nome do edifício/condomínio. |
| `endereco_id` | FK | Chave estrangeira para `Endereco`. |

### 5.4. Entidade `Vistoria`

| Campo | Tipo de Dado | Descrição |
| :--- | :--- | :--- |
| `id` | UUID/INT | Identificador único. |
| `codigo` | VARCHAR(50) | Código da vistoria/solicitação. |
| `status` | ENUM | 'Solicitada', 'Designada', 'Em Andamento', 'Concluída', 'Cancelada'. |
| `tipo` | VARCHAR(50) | Tipo de vistoria (e.g., Entrada, Saída). |
| `imovel_id` | FK | Chave estrangeira para `Imovel`. |
| `cliente_id` | FK | Chave estrangeira para `Pessoa` (Cliente/Responsável). |
| `data_agendamento` | DATETIME | Data e hora agendada. |
| `observacoes` | TEXT | Observações gerais. |

### 5.5. Entidade `DocumentoAssinatura`

| Campo | Tipo de Dado | Descrição |
| :--- | :--- | :--- |
| `id` | UUID/INT | Identificador único. |
| `codigo` | VARCHAR(50) | Código do documento. |
| `assunto` | VARCHAR(255) | Assunto/Título do documento. |
| `data_criacao` | DATETIME | Data de criação. |
| `status` | ENUM | 'Assinado', 'Pendente assinatura', etc. |
| `arquivo_url` | VARCHAR(255) | URL do arquivo PDF/Documento. |

## 6. Próximos Passos

O próximo passo seria aprofundar o detalhamento das interações de usuário (wireframes/mockups) e a definição das APIs (endpoints, payloads) para cada funcionalidade listada.

Este documento serve como um ponto de partida robusto para o desenvolvimento do sistema.
