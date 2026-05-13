<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de integração com OpenAI
 * APROVEITADO de: application/services/OpenAIService.php
 * 
 * Funcionalidades:
 * - Transcrição de áudio (Whisper API)
 * - Processamento de texto (GPT)
 * - Extração de dados estruturados
 */
class OpenAIService
{
    private $apiKey;
    private $model;

    public function __construct()
    {
        $this->apiKey = $this->resolveApiKey();
        $this->model = $this->resolveModel();
    }

    public function generateLeadDiagnostic($leadProfile, $conversationHistory, $availableProperties = [])
    {
        $systemPrompt = "Você é um especialista imobiliário que prepara diagnósticos para corretores humanos.

Monte um relatório objetivo com os blocos: \n1. Perfil geral do cliente\n2. Capacidade financeira (inclua renda, orçamento e viabilidade)\n3. Preferências e gatilhos emocionais\n4. Riscos e pontos de atenção\n5. Sugestões de abordagem para o corretor.\n
Use apenas informações confirmadas. Se faltar algum dado relevante, sinalize como 'Pendentes'.";

        $profileJson = json_encode($leadProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $propertiesJson = json_encode($availableProperties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $userPrompt = "DADOS DO LEAD:\n$profileJson\n\nHISTÓRICO DA CONVERSA:\n$conversationHistory\n\nIMÓVEIS INDICADOS:\n$propertiesJson\n\nGere o diagnóstico conforme solicitado.";

        return $this->chatCompletion($systemPrompt, $userPrompt);
    }

    public function generateSimpleMessage($systemPrompt, $userPrompt): string
    {
        $result = $this->chatCompletion($systemPrompt, $userPrompt);

        return $result['success'] ? $result['content'] : '';
    }

    /**
     * Método genérico para chat com a OpenAI API
     * 
     * @param array $messages Array de mensagens no formato [['role' => 'system', 'content' => '...'], ...]
     * @param int|null $tenantId ID do tenant (opcional, para log)
     * @return array Resposta completa da API
     */
    public function chat(array $messages, $tenantId = null): array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $apiKey = $this->resolveApiKey();
        $model = $this->resolveModel();

        if ($apiKey === '') {
            Log::error('OpenAI Chat Error', [
                'tenant_id' => $tenantId,
                'error' => 'OpenAI API key not configured',
            ]);

            throw new \Exception('OpenAI API key not configured');
        }
        
        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1000
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            Log::error('OpenAI Chat Error', [
                'http_code' => $httpCode,
                'response' => $response,
                'curl_error' => $error,
                'tenant_id' => $tenantId
            ]);
            
            throw new \Exception('OpenAI API Error: ' . ($error ?: 'HTTP ' . $httpCode));
        }
        
        return json_decode($response, true);
    }

    /**
     * Transcrever áudio do WhatsApp usando Whisper API
     * 
     * @param string $audioPath Caminho do arquivo de áudio
     * @return array Resultado da transcrição
     */
    public function transcribeAudio($audioPath, ?string $contentType = null, ?string $originalFilename = null)
    {
        $url = 'https://api.openai.com/v1/audio/transcriptions';
        $apiKey = $this->resolveApiKey();

        if ($apiKey === '') {
            Log::error('OpenAI Transcription Error', [
                'error' => 'OpenAI API key not configured',
                'audio_path' => $audioPath,
            ]);

            return [
                'success' => false,
                'error' => 'OpenAI API key not configured',
            ];
        }

        $normalizedContentType = trim((string) explode(';', (string) $contentType)[0]);
        if ($normalizedContentType === '' && function_exists('mime_content_type')) {
            $normalizedContentType = (string) mime_content_type($audioPath);
        }

        if ($normalizedContentType === '') {
            $normalizedContentType = 'application/octet-stream';
        }

        $filename = $originalFilename ?: basename($audioPath);

        $file = new \CURLFile($audioPath, $normalizedContentType, $filename);

        $postFields = [
            'file' => $file,
            'model' => 'whisper-1',
            'language' => 'pt',
            'response_format' => 'json',
            'temperature' => 0.2
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // ⚡ Timeout de 30s para transcrição
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'text' => $data['text'] ?? ''
            ];
        }
        
        Log::error('OpenAI Transcription Error', [
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error
        ]);
        
        return [
            'success' => false,
            'error' => 'Transcription failed',
            'details' => $response
        ];
    }
    
    /**
     * Extrair dados estruturados do lead usando GPT
     * 
     * @param string $conversationHistory Histórico da conversa
     * @return array Dados extraídos
     */
    public function extractLeadData($conversationHistory)
    {
        $systemPrompt = "Você é um analista que lê conversas de atendimento imobiliário e transforma tudo em dados estruturados.

⚠️ FOQUE NAS ÚLTIMAS MENSAGENS - elas têm PRIORIDADE TOTAL!

Extraia SEMPRE um JSON com as seguintes chaves (use null se não houver dado):
{
  \"budget_min\": número (apenas dígitos, sem formatação),
  \"budget_max\": número (apenas dígitos, sem formatação),
  \"localizacao\": string (bairro ou região mencionada),
  \"quartos\": número inteiro,
  \"suites\": número inteiro,
  \"garagem\": número inteiro,
  \"caracteristicas_desejadas\": string,
  \"renda_mensal\": número (apenas dígitos, sem formatação),
  \"estado_civil\": string,
  \"composicao_familiar\": string,
  \"profissao\": string,
  \"fonte_renda\": string,
  \"financiamento_status\": string,
  \"prazo_compra\": string,
  \"objetivo_compra\": string,
  \"preferencia_tipo_imovel\": string,
  \"preferencia_bairro\": string,
  \"preferencia_lazer\": string,
  \"preferencia_seguranca\": string,
  \"observacoes_cliente\": string
}

⚠️ REGRAS CRÍTICAS:
1. Se houver múltiplos valores, SEMPRE escolha o MAIS RECENTE (última mensagem tem prioridade)
2. Renda mensal: converta valores como 150000 ou 5 mil para número puro
3. NÃO invente informações - retorne null se não tiver certeza
4. Retorne SOMENTE o JSON, sem texto adicional

Exemplos de extração:
- Cliente: 150000 ou minha renda mensal é de 150000 → renda_mensal: 150000
- Cliente: quero 3 quartos → quartos: 3";

        $userPrompt = "Conversa:\n\n" . $conversationHistory . "\n\nResponda apenas com o JSON solicitado. FOQUE NAS ÚLTIMAS MENSAGENS!";
        
        $result = $this->chatCompletion($systemPrompt, $userPrompt, null, 400);
        
        if ($result['success']) {
            $extracted = $this->decodeJsonObject($result['content'] ?? '');

            if (is_array($extracted)) {
                return [
                    'success' => true,
                    'data' => $extracted
                ];
            }

            Log::warning('OpenAI Lead Extraction Error', [
                'error' => 'Failed to parse JSON response',
                'content_preview' => substr((string) ($result['content'] ?? ''), 0, 300),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to parse JSON response'
            ];
        }
        
        return $result;
    }
    
    /**
     * Processar mensagem e gerar resposta contextual
     * 
     * @param string $message Mensagem do usuário
     * @param string $context Contexto da conversa
     * @param bool $isFromAudio Se a mensagem veio de transcrição de áudio
     * @param array $availableProperties Imóveis disponíveis para consulta
     * @return array Resposta gerada
     */
    public function processMessage($message, $context = '', $isFromAudio = false, $availableProperties = [], $leadData = null)
    {
        \App\Models\SystemLog::info(
            \App\Models\SystemLog::CATEGORY_IA,
            'process_message_start',
            'Iniciando processamento de mensagem com IA',
            [
                'message_length' => strlen($message),
                'has_context' => !empty($context),
                'is_audio' => $isFromAudio,
                'properties_count' => count($availableProperties)
            ]
        );
        
        $assistantName = $this->resolveAssistantName();
        $companyName = $this->resolveCompanyName();
        $audioInstruction = $isFromAudio
            ? "\n- O cliente acabou de enviar um ÁUDIO que foi transcrito. Responda de forma natural, mostrando que você OUVIU e ENTENDEU o que ele disse. Use expressões como 'Entendi!', 'Certo!', 'Perfeito!' para confirmar que você ouviu."
            : "";
        
        // Verificar TODOS os campos essenciais do cadastro (16 campos)
        $dadosFaltantes = [];
        if ($leadData) {
            // Prioridade 1: Dados cadastrais básicos (mais importantes)
            if (empty($leadData['nome'])) $dadosFaltantes[] = 'nome';
            if (empty($leadData['telefone'])) $dadosFaltantes[] = 'telefone';
            if (empty($leadData['email'])) $dadosFaltantes[] = 'email';
            
            // Prioridade 2: Dados financeiros (qualificação)
            if (empty($leadData['renda_mensal'])) $dadosFaltantes[] = 'renda mensal';
            if (empty($leadData['budget_min'])) $dadosFaltantes[] = 'orçamento mínimo';
            if (empty($leadData['budget_max'])) $dadosFaltantes[] = 'orçamento máximo';
            
            // Prioridade 3: Dados pessoais (perfil)
            if (empty($leadData['estado_civil'])) $dadosFaltantes[] = 'estado civil';
            if (empty($leadData['composicao_familiar'])) $dadosFaltantes[] = 'composição familiar';
            if (empty($leadData['profissao'])) $dadosFaltantes[] = 'profissão';
            if (empty($leadData['fonte_renda'])) $dadosFaltantes[] = 'fonte de renda';
            
            // Prioridade 4: Preferências de imóvel (matching)
            if (empty($leadData['localizacao'])) $dadosFaltantes[] = 'localização desejada';
            if (empty($leadData['quartos'])) $dadosFaltantes[] = 'quantidade de quartos';
            if (empty($leadData['objetivo_compra'])) $dadosFaltantes[] = 'objetivo da compra';
            if (empty($leadData['preferencia_tipo_imovel'])) $dadosFaltantes[] = 'tipo de imóvel';
            if (empty($leadData['preferencia_bairro'])) $dadosFaltantes[] = 'bairro preferido';
        } else {
            $dadosFaltantes = [
                'nome', 'telefone', 'email', 'renda mensal', 'orçamento mínimo', 'orçamento máximo',
                'estado civil', 'composição familiar', 'profissão', 'fonte de renda',
                'localização desejada', 'quantidade de quartos', 'objetivo da compra',
                'tipo de imóvel', 'bairro preferido'
            ];
        }
        
        // Criar contexto de coleta de dados (perguntar por campos prioritários primeiro)
        $dataCollectionContext = "";
        if (!empty($dadosFaltantes)) {
            // Limitar a 5 campos mais importantes para não sobrecarregar o prompt
            $camposPrioritarios = array_slice($dadosFaltantes, 0, 5);
            
            $dataCollectionContext = "\n\n⚠️ DADOS FALTANTES DO CLIENTE (em ordem de prioridade): " . implode(', ', $camposPrioritarios) . "\n";
            $dataCollectionContext .= "⚠️ INSTRUÇÃO: Colete dados de forma NATURAL seguindo o fluxo do treinamento.\n";
            $dataCollectionContext .= "⚠️ Priorize sempre o PRIMEIRO dado faltante da lista acima.\n";
            $dataCollectionContext .= "⚠️ Exemplos de abordagem (SEMPRE com sugestão de resposta):\n";
            $dataCollectionContext .= "  - Nome: 'Só preciso do seu nome completo para registrar. Por exemplo: \"Meu nome é Ana Paula Souza\"'\n";
            $dataCollectionContext .= "  - Email: 'Qual seu email? Pode ser: \"meu.email@gmail.com\"'\n";
            $dataCollectionContext .= "  - Renda: 'Me conte sobre sua renda. Exemplo: \"Tenho carteira assinada, ganho R\$4.000\" ou \"Sou autônoma\"'\n";
            $dataCollectionContext .= "  - Orçamento: 'Qual o valor máximo? Pode dizer: \"Até 450 mil\" ou \"Entre 300 e 400 mil\"'\n";
            $dataCollectionContext .= "  - Localização: 'Qual bairro ou região? Exemplo: \"Na Pampulha\" ou \"Qualquer bairro central\"'\n";
            $dataCollectionContext .= "  - Quartos: 'Quantos quartos? Por exemplo: \"Preciso de 3 quartos\" ou \"2 quartos está bom\"'\n";
            $dataCollectionContext .= "⚠️ SEMPRE sugira como o cliente pode responder!\n";
        }
        
        // Preparar contexto de imóveis disponíveis
        $propertiesContext = "";
        if (!empty($availableProperties)) {
            $propertiesContext = "\n\n=== IMÓVEIS DISPONÍVEIS NO BANCO DE DADOS (DADOS REAIS) ===\n";
            foreach ($availableProperties as $prop) {
                $dormitorios = $prop['dormitorios'] ?? 0;
                $suites = $prop['suites'] ?? 0;
                $totalQuartos = $dormitorios + $suites;
                
                // Processar imagens (pode ser JSON string, array, ou null)
                $imagens = $prop['imagens'] ?? null;
                
                // Laravel pode retornar como array deserializado ou string JSON
                if (is_string($imagens) && !empty($imagens)) {
                    $decoded = json_decode($imagens, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $imagens = $decoded;
                    } else {
                        $imagens = null;
                    }
                }
                // Se já vier como array, mantém
                
                // Validar se é array e tem elementos
                $hasImages = is_array($imagens) && !empty($imagens);
                $imageLinks = '';
                
                if ($hasImages) {
                    // Extrair URLs das imagens (pode ser objeto {url, destaque} ou string direta)
                    $validImages = [];
                    foreach ($imagens as $img) {
                        if (is_string($img) && !empty($img)) {
                            // String direta (URL)
                            $validImages[] = $img;
                        } elseif (is_array($img) && isset($img['url']) && !empty($img['url'])) {
                            // Objeto com chave 'url'
                            $validImages[] = $img['url'];
                        }
                    }
                    
                    if (!empty($validImages)) {
                        $imageLinks = implode("\n  ", array_slice($validImages, 0, 1));
                    }
                }
                
                $propertiesContext .= sprintf(
                    "- Código: %s | Tipo: %s | Bairro: %s | Valor: R$ %s | Total de Quartos: %d (sendo %d dormitórios + %d suítes)\n",
                    $prop['codigo_imovel'] ?? 'N/A',
                    $prop['tipo_imovel'] ?? 'N/A',
                    $prop['bairro'] ?? 'N/A',
                    number_format($prop['valor_venda'] ?? 0, 2, ',', '.'),
                    $totalQuartos,
                    $dormitorios,
                    $suites
                );
                
                if (!empty($imageLinks)) {
                    $propertiesContext .= "  📸 Fotos disponíveis:\n  " . $imageLinks . "\n";
                }
            }
            $propertiesContext .= "\n⚠️ CRÍTICO: SEMPRE consulte esta lista ANTES de responder. NUNCA diga que não temos algo sem verificar!\n";
            $propertiesContext .= "⚠️ IMPORTANTE: Quando o cliente pedir 'X quartos', considere o TOTAL (dormitórios + suítes)!\n";
            $propertiesContext .= "⚠️ FOTOS: Quando o cliente pedir fotos de um imóvel, ENVIE os links diretamente se disponíveis acima!\n";
        }
        
        // NOVO: Buscar prompt personalizado do admin (prevalece sobre o default)
        $customPrompt = AppSetting::getValue('ai_prompt_custom', null);
        
        if (!empty($customPrompt)) {
            // Admin configurou prompt customizado - SUBSTITUI completamente o prompt padrão
            Log::info('[OpenAI] Usando prompt CUSTOMIZADO do administrador', [
                'length' => strlen($customPrompt),
                'preview' => substr($customPrompt, 0, 100)
            ]);
            
            // Injeta variáveis no prompt customizado
            $systemPrompt = str_replace('{$assistantName}', $assistantName, $customPrompt);
            $systemPrompt = str_replace('{$companyName}', $companyName, $systemPrompt);
            $systemPrompt = str_replace('{$audioInstruction}', $audioInstruction, $systemPrompt);
            $systemPrompt = str_replace('{$propertiesContext}', $propertiesContext, $systemPrompt);
            $systemPrompt .= "\n\nPERSONALIDADE FIXA DA {$assistantName}: gentil, acolhedora e cuidadosa. Faça o cliente sentir que foi entendido antes de pedir novos dados. Recapitule o que ele já informou quando isso ajudar.";
            $systemPrompt .= "\nREGRA FIXA: responda primeiro ao que o cliente perguntou; seja cordial e objetivo (cerca de 2 a 6 frases curtas, até ~120 palavras). Se fizer sentido, termine com no máximo uma pergunta.";
            $systemPrompt .= "\nSE NÃO HOUVER IMÓVEL COMPATÍVEL: não use resposta genérica de sem-match. Diga que não quer enviar algo fora do perfil, recapitule os critérios e informe que a equipe/corretor vai conferir opções próximas manualmente.";
        } else {
            // Usa prompt padrão do sistema
            Log::info('[OpenAI] Usando prompt PADRÃO do sistema');
            
            $systemPrompt = "Você é {$assistantName}, assistente imobiliário virtual da {$companyName}.

⚡ FORMATO DE RESPOSTA:
- Prioridade absoluta: responder diretamente ao que o cliente perguntou na última mensagem (dúvida sobre imóvel, valor, documentação, visita, financiamento etc.)
- Tom gentil, acolhedor, cuidadoso e empático; use \"Bom dia/Boa tarde/Boa noite\" apenas na primeira resposta do fluxo se ainda não cumprimentou
- Seja claro em 2 a 6 frases curtas (até cerca de 120 palavras) — não cortar a resposta no meio da explicação
- Se ainda faltar dado essencial para ajudar, faça no máximo UMA pergunta objetiva por vez
- Use emojis com moderação{$audioInstruction}

🎯 SEU PAPEL:
Você é o primeiro contato do cliente. Seu objetivo é QUALIFICAR o lead de forma natural e acolhedora, coletando informações essenciais para que um corretor humano dê continuidade ao atendimento.

📋 FLUXO DE QUALIFICAÇÃO (siga nesta ordem):

ETAPA 1 — ACOLHIMENTO:
- Confirme o nome do cliente
- Se veio por anúncio de imóvel específico, confirme o interesse naquele imóvel
- Pergunte: \"Em qual bairro ou região você está buscando?\"

ETAPA 2 — ORÇAMENTO:
- Pergunte a faixa de valor que o cliente tem em mente
- Ex: \"Qual faixa de valor você está considerando?\"
- Se mencionou financiamento, pergunte se já tem aprovação ou simulação

ETAPA 3 — PRAZO E PERFIL:
- Pergunte o prazo: \"Está buscando para os próximos meses ou é uma pesquisa mais inicial?\"
- Pergunte quantos quartos precisa
- Tipo de imóvel (casa, apartamento, etc.)

ETAPA 4 — APRESENTAÇÃO DE IMÓVEIS:
- Se temos imóveis compatíveis no banco de dados, apresente até 3 opções numeradas
- Formato: 1️⃣ [Tipo] em [Bairro] — [quartos]q — R$ [valor]
- Pergunte qual interessou mais

ETAPA 5 — ENCAMINHAMENTO:
- Após coletar bairro + orçamento + prazo, informe que um corretor especializado vai entrar em contato
- Mensagem: \"Perfeito! Vou repassar suas informações para um corretor especializado na região. Ele vai te contatar em breve com as melhores opções. 👍\"

{$propertiesContext}

📊 CLASSIFICAÇÃO DO CLIENTE (use internamente):
- QUENTE: Tem orçamento definido + bairro definido + prazo curto (até 3 meses)
- MORNO: Tem interesse real mas falta definir orçamento ou prazo
- FRIO: Pesquisa inicial, sem urgência, sem definições claras

📝 REGRAS DE LINGUAGEM:
- Trate SEMPRE por \"você\" (nunca \"tu\" ou \"senhor/senhora\" a menos que o cliente peça)
- Use linguagem clara e acessível
- NUNCA invente dados de imóveis — use APENAS os dados reais fornecidos
- Se não tiver imóveis compatíveis, não use resposta genérica. Recapitule a busca do cliente, diga que não quer mandar imóvel fora do perfil e informe que a equipe/corretor vai conferir opções próximas manualmente.
- Nunca use a resposta genérica antiga de sem-match; sempre contextualize a busca do cliente.
- NUNCA diga que é um robô/IA — você é {$assistantName}, assistente da {$companyName}

{$dataCollectionContext}

⚠️ FORMATAÇÃO DE IMÓVEIS:
Liste até 3 opções por vez:
1️⃣ [Tipo] em [Bairro], [quartos]q — R$ [valor]
2️⃣ [Tipo] em [Bairro], [quartos]q — R$ [valor]

Após listar: \"Qual desses te interessou mais?\"

❌ NUNCA FAÇA:
- Inventar imóveis ou valores
- Fazer múltiplas perguntas de uma vez
- Ser prolixo sem necessidade (preferir frases curtas, mas completas)
- Prometer visitas ou agendamentos (isso é papel do corretor)

✅ SEMPRE FAÇA:
- Confirmar dados coletados: \"Anotei, bairro X e faixa de R$ Y ✅\"
- Manter o diálogo fluindo com perguntas
- Encaminhar para corretor quando tiver bairro + orçamento + prazo";
        }
        
        // Adicionar contexto de dados coletados (se houver)
        if (!empty($dataCollectionContext)) {
            $systemPrompt .= "\n\n" . $dataCollectionContext;
        }

        $userPrompt = ($context ? "Contexto anterior:\n$context\n\n" : "") . "Cliente: $message\n\nResponda:";
        $model = $this->resolveModel();
        
        \App\Models\SystemLog::debug(
            \App\Models\SystemLog::CATEGORY_IA,
            'send_to_openai',
            'Enviando prompt para OpenAI',
            ['model' => $model, 'prompt_length' => strlen($userPrompt)]
        );
        
        $result = $this->chatCompletion($systemPrompt, $userPrompt, null, 520);
        
        if ($result['success']) {
            \App\Models\SystemLog::info(
                \App\Models\SystemLog::CATEGORY_IA,
                'process_message_success',
                'Mensagem processada com sucesso',
                ['response_length' => strlen($result['content'])]
            );
        } else {
            \App\Models\SystemLog::error(
                \App\Models\SystemLog::CATEGORY_IA,
                'process_message_error',
                'Erro ao processar mensagem',
                ['error' => $result['error'] ?? 'Unknown']
            );
        }
        
        return $result;
    }
    
    /**
     * Fazer chamada à API de Chat Completion
     */
    private function chatCompletion($systemPrompt, $userPrompt, ?int $maxWords = null, int $maxTokens = 512)
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $apiKey = $this->resolveApiKey();
        $model = $this->resolveModel();

        if ($apiKey === '') {
            Log::error('OpenAI Chat Completion Error', [
                'error' => 'OpenAI API key not configured',
            ]);

            return [
                'success' => false,
                'error' => 'OpenAI API key not configured'
            ];
        }
        
        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => $maxTokens
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? '';
            
            return [
                'success' => true,
                'content' => $maxWords === null ? trim($content) : $this->limitResponseWords($content, $maxWords)
            ];
        }
        
        Log::error('OpenAI Chat Completion Error', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        
        return [
            'success' => false,
            'error' => 'Chat completion failed'
        ];
    }

    private function limitResponseWords(string $content, int $maxWords = 20): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');

        if ($normalized === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= $maxWords) {
            return $normalized;
        }

        return implode(' ', array_slice($words, 0, $maxWords));
    }

    private function decodeJsonObject(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveAssistantName(): string
    {
        // 1. Tentar via tenant
        $tenant = $this->currentTenant();
        if ($tenant) {
            $tenantName = $tenant->getAiAssistantName();
            if (!empty($tenantName)) {
                return $tenantName;
            }
        }

        // 2. Fallback: AppSetting / env
        $default = env('AI_ASSISTANT_NAME', 'Teresa');
        $name = AppSetting::getValue('ai_name', $default);

        if (is_array($name)) {
            $name = $name['value'] ?? reset($name);
        }

        $name = trim((string) $name);

        return $name !== '' ? $name : $default;
    }

    private function resolveCompanyName(): string
    {
        $tenant = $this->currentTenant();
        if ($tenant) {
            return $tenant->getCompanyName();
        }

        return env('COMPANY_NAME', 'Imobiliária');
    }

    private function currentTenant()
    {
        if (!app()->bound('tenant')) {
            return null;
        }

        try {
            return app()->make('tenant');
        } catch (\Throwable $exception) {
            Log::warning('[OpenAIService] Tenant indisponivel no container', [
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveApiKey(): string
    {
        $tenant = $this->currentTenant();
        if ($tenant) {
            $tenantApiKey = trim((string) $tenant->getOpenAiApiKey());
            if ($tenantApiKey !== '') {
                return $tenantApiKey;
            }
        }

        $candidates = [
            env('EXCLUSIVA_OPENAI_API_KEY'),
            env('OPENAI_API_KEY'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveModel(): string
    {
        $tenant = $this->currentTenant();
        if ($tenant) {
            $tenantModel = trim((string) $tenant->getOpenAiModel());
            if ($tenantModel !== '') {
                return $tenantModel;
            }
        }

        $candidates = [
            env('EXCLUSIVA_OPENAI_MODEL'),
            env('OPENAI_MODEL'),
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return 'gpt-4o-mini';
    }
}
