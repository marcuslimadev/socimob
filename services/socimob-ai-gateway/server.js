import express from 'express';

const app = express();
app.use(express.json({ limit: '1mb' }));

const PORT = Number(process.env.PORT || 3100);
const API_KEY = String(process.env.SOCIMOB_AI_API_KEY || '').trim();
const OLLAMA_BASE_URL = String(process.env.OLLAMA_BASE_URL || 'http://127.0.0.1:11434').replace(/\/$/, '');
const DEFAULT_MODEL = String(process.env.OLLAMA_MODEL || 'llama3.1:8b');
const MAX_HISTORY_CHARS = Number(process.env.MAX_HISTORY_CHARS || 6000);
const MAX_PROPERTIES = Number(process.env.MAX_PROPERTIES || 8);

function requireAuth(req, res, next) {
  if (!API_KEY) return next();

  const token = String(req.headers.authorization || '').replace(/^Bearer\s+/i, '').trim();
  if (token !== API_KEY) {
    return res.status(401).json({ success: false, error: 'Unauthorized' });
  }

  return next();
}

function clampText(value, maxChars) {
  const text = String(value || '').trim();
  if (text.length <= maxChars) return text;
  return text.slice(-maxChars);
}

function propertyLine(property) {
  const bedrooms = Number(property?.dormitorios || 0) + Number(property?.suites || 0);
  const rent = Number(property?.valor_aluguel || 0);
  const sale = Number(property?.valor_venda || 0);
  const price = rent > 0
    ? `aluguel R$ ${rent.toLocaleString('pt-BR')}`
    : sale > 0
      ? `venda R$ ${sale.toLocaleString('pt-BR')}`
      : 'valor sob consulta';

  return [
    `Codigo ${property?.codigo_imovel || property?.codigo || 'N/A'}`,
    property?.tipo_imovel || 'Imovel',
    [property?.bairro, property?.cidade].filter(Boolean).join(', ') || 'local nao informado',
    `${bedrooms || '?'} quartos`,
    price,
  ].join(' | ');
}

function buildPrompt(body) {
  const assistantName = body.assistant_name || 'Teresa';
  const companyName = body.company_name || 'Exclusiva Lar Imoveis';
  const properties = Array.isArray(body.properties) ? body.properties.slice(0, MAX_PROPERTIES) : [];
  const lead = body.lead && typeof body.lead === 'object' ? body.lead : {};
  const missing = [];

  if (!lead.objetivo_compra) missing.push('compra ou aluguel');
  if (!lead.localizacao && !lead.preferencia_bairro) missing.push('bairro ou regiao');
  if (!lead.budget_max && !lead.budget_min) missing.push('faixa de valor');
  if (!lead.quartos) missing.push('quantidade de quartos');
  if (!lead.prazo_compra) missing.push('prazo');
  if (!lead.renda_mensal) missing.push('renda aproximada');

  const propertyContext = properties.length
    ? properties.map((item) => `- ${propertyLine(item)}`).join('\n')
    : 'Nenhum imovel real foi enviado nesta chamada.';

  const leadContext = Object.entries(lead)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([key, value]) => `- ${key}: ${value}`)
    .join('\n') || 'Nenhum dado estruturado do lead.';

  return {
    system: `${body.system_prompt || ''}

REGRAS OPERACIONAIS SOCIMOB:
- Voce e ${assistantName}, da ${companyName}.
- Responda sempre em portugues do Brasil.
- Seja educada, consultiva e acolhedora.
- Nao seja seca, nao pressione e nao repita pergunta respondida.
- Faca no maximo uma pergunta por mensagem.
- Nao invente imoveis: use somente os imoveis reais abaixo.
- Se o cliente der codigo, data, bairro, quartos, renda ou prazo, reconheca antes de avancar.
- Se faltar informacao, peca apenas o primeiro item faltante.
- Nao diga que e IA ou robo.
- Limite normal: ate ${Number(body.max_words || 120)} palavras.`,
    user: `HISTORICO:
${clampText(body.history, MAX_HISTORY_CHARS)}

DADOS DO LEAD:
${leadContext}

DADOS FALTANTES:
${missing.length ? missing.join(', ') : 'nenhum dado principal pendente'}

IMOVEIS REAIS DISPONIVEIS:
${propertyContext}

MENSAGEM ATUAL DO CLIENTE:
${body.message || ''}

Responda como ${assistantName}:`,
  };
}

async function ollamaChat(body) {
  const prompt = buildPrompt(body);
  const model = body.model || DEFAULT_MODEL;

  const response = await fetch(`${OLLAMA_BASE_URL}/api/chat`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      model,
      stream: false,
      messages: [
        { role: 'system', content: prompt.system },
        { role: 'user', content: prompt.user },
      ],
      options: {
        temperature: 0.35,
        num_predict: 220,
      },
    }),
  });

  if (!response.ok) {
    const text = await response.text();
    throw new Error(`Ollama ${response.status}: ${text.slice(0, 500)}`);
  }

  const data = await response.json();
  const content = data?.message?.content;
  if (!content || typeof content !== 'string') {
    throw new Error('Resposta invalida do Ollama');
  }

  return { content: content.trim(), model };
}

app.get('/health', requireAuth, async (_req, res) => {
  try {
    const response = await fetch(`${OLLAMA_BASE_URL}/api/tags`);
    const data = response.ok ? await response.json() : null;
    const models = Array.isArray(data?.models) ? data.models.map((item) => item.name).filter(Boolean) : [];

    return res.json({
      success: response.ok,
      provider: 'ollama',
      model: DEFAULT_MODEL,
      model_available: models.includes(DEFAULT_MODEL),
      models,
    });
  } catch (error) {
    return res.status(503).json({
      success: false,
      provider: 'ollama',
      model: DEFAULT_MODEL,
      error: error.message,
    });
  }
});

app.post('/chat', requireAuth, async (req, res) => {
  try {
    const result = await ollamaChat(req.body || {});
    return res.json({
      success: true,
      content: result.content,
      model: result.model,
      provider: 'socimob-ai-gateway',
    });
  } catch (error) {
    return res.status(502).json({
      success: false,
      error: error.message,
    });
  }
});

app.listen(PORT, () => {
  console.log(`Socimob AI Gateway listening on port ${PORT}`);
});
