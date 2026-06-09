# Socimob AI Gateway

Servico Node para hospedar a IA propria do Socimob em outro dominio e expor uma API simples para o CRM.

## Arquitetura

```text
Socimob CRM -> https://ia.seudominio.com/chat -> Node gateway -> Ollama -> modelo open-source
```

O CRM nao chama OpenAI quando o provedor `socimob_ai` esta ativo. Ele chama este gateway por HTTP.

## Servidor recomendado

Use um VPS separado da hospedagem do site.

Minimo pratico:

- 4 vCPU
- 8 GB RAM
- 40 GB disco
- Ubuntu 22.04 ou 24.04

Melhor:

- 8 vCPU
- 16 GB RAM
- GPU se quiser respostas mais rapidas

## Instalar no VPS

```bash
curl -fsSL https://ollama.com/install.sh | sh
ollama pull llama3.1:8b

cd services/socimob-ai-gateway
cp .env.example .env
npm install
npm start
```

Em producao, rode com `pm2`, `systemd` ou painel Node do provedor.

## Variaveis do gateway

```bash
PORT=3100
SOCIMOB_AI_API_KEY=uma-chave-forte
OLLAMA_BASE_URL=http://127.0.0.1:11434
OLLAMA_MODEL=llama3.1:8b
```

## Variaveis no Socimob

No `.env` do Socimob online:

```bash
SOCIMOB_AI_BASE_URL=https://ia.seudominio.com
SOCIMOB_AI_API_KEY=a-mesma-chave-do-gateway
SOCIMOB_AI_MODEL=llama3.1:8b
SOCIMOB_AI_TIMEOUT=60
```

Depois, no painel:

```text
Configurações -> Atendimento via IA -> Socimob IA
```

## Endpoints

`GET /health`

Retorna status do gateway e modelos disponíveis no Ollama.

`POST /chat`

Payload esperado pelo CRM:

```json
{
  "message": "Mensagem atual do cliente",
  "history": "Historico da conversa",
  "lead": {},
  "properties": [],
  "assistant_name": "Teresa",
  "company_name": "Exclusiva Lar Imoveis"
}
```

Resposta:

```json
{
  "success": true,
  "content": "Resposta da Teresa",
  "model": "llama3.1:8b",
  "provider": "socimob-ai-gateway"
}
```

## Sobre treinamento

`C:\Treinamento IA\treinamento_imobiliaria.jsonl` e um dataset inicial, nao um modelo pronto.

O caminho seguro e:

1. Rodar um modelo pre-treinado open-source.
2. Coletar conversas reais boas.
3. Anonimizar nomes, telefones, CPFs e enderecos sensiveis.
4. Gerar dataset grande de exemplos.
5. Fazer fine-tuning/LoRA em ambiente separado.
6. Trocar `OLLAMA_MODEL` para o modelo treinado.
