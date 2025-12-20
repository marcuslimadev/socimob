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
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model = env('OPENAI_MODEL', 'gpt-4o-mini');
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

    /**
     * Transcrever áudio do WhatsApp usando Whisper API
     * 
     * @param string $audioPath Caminho do arquivo de áudio
     * @return array Resultado da transcrição
     */
    public function transcribeAudio($audioPath)
    {
        $url = 'https://api.openai.com/v1/audio/transcriptions';
        
        $file = new \CURLFile($audioPath, 'audio/ogg', 'audio.ogg');
        
        $postFields = [
            'file' => $file,
            'model' => 'whisper-1',
            'language' => 'pt'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
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
  \"cpf\": CPF apenas com 11 dígitos (sem pontos ou traços),
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
2. Extraia CPF mesmo sem formatação (ex: 91963214234)
3. Renda mensal: converta valores como \"150000\" ou \"5 mil\" para número puro
4. NÃO invente informações - retorne null se não tiver certeza
5. Retorne SOMENTE o JSON, sem texto adicional

Exemplos de extração:
- Cliente: \"Meu CPF é 91963214234\" → {\"cpf\": \"91963214234\"}
- Cliente: \"150000\" ou \"minha renda mensal é de 150000\" → {\"renda_mensal\": 150000}
- Cliente: \"quero 3 quartos\" → {\"quartos\": 3}";

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
            if (empty($leadData['cpf'])) $dadosFaltantes[] = 'CPF';
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
                'nome', 'telefone', 'CPF', 'email', 'renda mensal', 'orçamento mínimo', 'orçamento máximo',
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
            $dataCollectionContext .= "⚠️ INSTRUÇÃO: Em TODA resposta, de forma SUTIL e GENTIL, pergunte por UM dos dados faltantes.\n";
            $dataCollectionContext .= "⚠️ Priorize sempre o PRIMEIRO dado faltante da lista acima.\n";
            $dataCollectionContext .= "⚠️ Exemplos de abordagem:\n";
            $dataCollectionContext .= "  - CPF: 'Ah, me passa seu CPF pra gente agilizar depois?'\n";
            $dataCollectionContext .= "  - Email: 'Qual seu email pra te enviar os detalhes?'\n";
            $dataCollectionContext .= "  - Renda: 'Pra te ajudar melhor, qual sua renda mensal?'\n";
            $dataCollectionContext .= "  - Orçamento: 'Qual o valor máximo que você pode investir?'\n";
            $dataCollectionContext .= "  - Estado civil: 'Você é casado(a)? Isso ajuda no financiamento'\n";
            $dataCollectionContext .= "  - Profissão: 'Qual sua profissão? Só pra adequar as opções'\n";
            $dataCollectionContext .= "  - Localização: 'Qual bairro ou região você prefere?'\n";
            $dataCollectionContext .= "  - Quartos: 'Quantos quartos você precisa no mínimo?'\n";
            $dataCollectionContext .= "  - Objetivo: 'É pra morar ou investimento?'\n";
            $dataCollectionContext .= "⚠️ SEMPRE pergunte ao final da resposta, não no meio!\n";
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
        
        $systemPrompt = "Você é {$assistantName}, atendente virtual da Exclusiva Lar Imóveis, uma imobiliária especializada.

Seu objetivo é:
- Se apresentar como {$assistantName} e confirmar como o cliente prefere ser chamado
- Ser cordial, profissional mas CASUAL e leve na conversa
- **COLETAR DADOS IMPORTANTES de forma PERSISTENTE mas GENTIL**
- SEMPRE terminar sua resposta perguntando por UM dado faltante (veja contexto abaixo)
- Quando o cliente fornecer CPF ou renda, AGRADEÇA e confirme que registrou
- Não fazer muitas perguntas de uma vez - 1 pergunta de dados por resposta
- Quando receber documentos, avisar que um corretor validará
- Manter tom conversacional e amigável{$audioInstruction}
{$propertiesContext}
{$dataCollectionContext}

REGRAS CRÍTICAS:
- Respostas curtas e diretas (máximo 3 linhas)
- ⚠️ SEMPRE termine com uma pergunta sobre um dado faltante (CPF, renda, orçamento, localização ou quartos)
- ⚠️ Seja SUTIL: não diga \"preciso\" ou \"é obrigatório\", diga \"pra te ajudar melhor\" ou \"só pra agilizar\"
- ⚠️ NUNCA diga que não temos um imóvel sem CONSULTAR a lista acima
- ⚠️ Quando o cliente pedir X quartos, considere TOTAL (dormitórios + suítes)
- ⚠️ FOTOS: Se houver links de fotos acima (começando com http), ENVIE-OS diretamente
- ⚠️ FOTOS: Se NÃO houver links acima, diga: 'Vou solicitar as fotos deste imóvel para o corretor e te envio em breve!'
- ⚠️ NUNCA invente links de fotos - apenas envie se estiverem listados acima
- ⚠️ Se cliente informar CPF, SEMPRE confirme: 'Perfeito! CPF XXX.XXX.XXX-XX registrado ✅'
- ⚠️ Se cliente informar renda, SEMPRE confirme: 'Ótimo! Renda de R$ X.XXX registrada ✅'
- ⚠️ Ao listar imóveis: Código, Valor, Bairro, Quartos (dormitórios/suítes) e 2-3 diferenciais
- Sobre imóveis específicos: responda OBJETIVAMENTE o que souber
- Não prometa enviar imóveis, fotos ou detalhes se não conseguir entregar na MESMA resposta. Se precisar de ajuda humana, diga que um corretor enviará.
- Não invente dados - se não souber, diga que o corretor irá responder

EXEMPLOS DE BOA ABORDAGEM:
Cliente: 'Quero um apartamento de 2 quartos'
Você: 'Temos várias opções de 2 quartos! Ah, e qual bairro você prefere? 😊'

Cliente: 'Tem algum no Centro?'
Você: 'Sim! Temos apartamentos no Centro a partir de R$ 300mil. E só pra eu te ajudar melhor, qual sua renda mensal?'

Cliente: 'Minha renda é 5 mil'
Você: 'Ótimo! Renda de R$ 5.000 registrada ✅ Me passa seu CPF também pra agilizar?'";

        $userPrompt = ($context ? "Contexto anterior:\n$context\n\n" : "") . "Cliente: $message\n\nResponda:";
        
        return $this->chatCompletion($systemPrompt, $userPrompt);
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
