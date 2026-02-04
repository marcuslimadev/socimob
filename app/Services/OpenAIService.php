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
        $this->apiKey = env('EXCLUSIVA_OPENAI_API_KEY');
        $this->model = env('EXCLUSIVA_OPENAI_MODEL', 'gpt-4o-mini');
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
        
        $data = [
            'model' => $this->model,
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
            'Authorization: Bearer ' . $this->apiKey
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
    public function transcribeAudio($audioPath)
    {
        $url = 'https://api.openai.com/v1/audio/transcriptions';

        // Usa MP3 após conversão para melhor compatibilidade
        $file = new \CURLFile($audioPath, 'audio/mpeg', 'audio.mp3');

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
            'Authorization: Bearer ' . $this->apiKey
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
        
        $result = $this->chatCompletion($systemPrompt, $userPrompt);
        
        if ($result['success']) {
            try {
                $extracted = json_decode($result['content'], true);
                return [
                    'success' => true,
                    'data' => $extracted
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse JSON response'
                ];
            }
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
                        // Pegar primeiras 5 imagens para enviar à IA
                        $imageLinks = implode("\n  ", array_slice($validImages, 0, 5));
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
            $systemPrompt = str_replace('{$audioInstruction}', $audioInstruction, $systemPrompt);
            $systemPrompt = str_replace('{$propertiesContext}', $propertiesContext, $systemPrompt);
        } else {
            // Usa prompt padrão do sistema
            Log::info('[OpenAI] Usando prompt PADRÃO do sistema');
            
            $systemPrompt = "Você é {$assistantName}, assistente imobiliário inteligente e empático da Exclusiva Lar Imóveis.

⚡ FORMATO DE RESPOSTA (CRÍTICO):
- MÁXIMO 200 CARACTERES por resposta
- Seja CURTO, DIRETO e OBJETIVO
- SEMPRE termine com UMA pergunta para o cliente
- Use emojis moderadamente
- Divida informações longas em múltiplas mensagens curtas
- Priorize clareza sobre completude

🎯 SEU PAPEL:
- Conduzir TOTALMENTE o usuário em todas as etapas do atendimento
- NUNCA deixar o usuário com dúvida
- SEMPRE sugerir exemplos de resposta
- NUNCA quebrar o fluxo
- Agir como diretor e roteirista da conversa
- Aplicar todas as regras do funil imobiliário brasileiro

📋 REGRAS DE OURO:
1. SEMPRE mostre imóveis ANTES de pedir dados pessoais (quando aplicável)
2. Ao listar imóveis, use NUMERAÇÃO (1️⃣, 2️⃣, 3️⃣) e formato claro
3. SEMPRE sugira como o cliente pode responder (exemplos explícitos)
4. Uma pergunta de cada vez - não sobrecarregue
5. Seja CASUAL mas profissional{$audioInstruction}

{$propertiesContext}

📝 FLUXO DE ATENDIMENTO (RESUMIDO):

ETAPA 1 - ENTENDER:
→ Mostre até 3 imóveis numerados
→ Pergunte: 'Qual te interessou?'

ETAPA 2 - NOME:
→ 'Seu nome completo?'

ETAPA 3 - RENDA:
→ 'Qual sua renda mensal?'

ETAPA 4 - DOCS (se aplicável):
→ 'Pode enviar RG e contra-cheque?'

{$dataCollectionContext}

⚠️ FORMATAÇÃO DE IMÓVEIS (CONCISA):
Liste até 3 opções por vez, formato curto:
1️⃣ [Tipo] [q]q, R$[valor] — [diferencial]
Exemplo: 1️⃣ Apto 2q, R$299k — Perto lagoa

Após listar, pergunte: 'Qual te interessou?'

🎤 MENSAGENS CURTAS:
- SEMPRE termine com pergunta específica
- Exemplos: 'Qual te interessou?' ou 'Pode me falar sua renda?'
- Se cliente hesitar: 'Quer ver mais opções?'

❌ NÃO FAÇA:
- Respostas longas (máx. 200 caracteres!)
- Múltiplas perguntas de uma vez
- Inventar dados de imóveis
- Pedir docs antes de mostrar imóveis

✅ SEMPRE FAÇA:
- Resposta curta + pergunta
- Confirme dados: 'R$ 5k registrado ✅'
- Mantenha tom empático";
        }
        
        // Adicionar contexto de dados coletados (se houver)
        if (!empty($dataCollectionContext)) {
            $systemPrompt .= "\n\n" . $dataCollectionContext;
        }

        $userPrompt = ($context ? "Contexto anterior:\n$context\n\n" : "") . "Cliente: $message\n\nResponda:";
        
        \App\Models\SystemLog::debug(
            \App\Models\SystemLog::CATEGORY_IA,
            'send_to_openai',
            'Enviando prompt para OpenAI',
            ['model' => $this->model, 'prompt_length' => strlen($userPrompt)]
        );
        
        $result = $this->chatCompletion($systemPrompt, $userPrompt);
        
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
    private function chatCompletion($systemPrompt, $userPrompt)
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
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
                'content' => trim($content)
            ];
        }
        
        Log::error('OpenAI Transcription Error', [
            'http_code' => $httpCode,
            'response' => $response
        ]);
        
        return [
            'success' => false,
            'error' => 'Chat completion failed'
        ];
    }

    private function resolveAssistantName(): string
    {
        $default = env('AI_ASSISTANT_NAME', 'Teresa');
        $name = AppSetting::getValue('ai_name', $default);

        if (is_array($name)) {
            $name = $name['value'] ?? reset($name);
        }

        $name = trim((string) $name);

        return $name !== '' ? $name : $default;
    }
}
