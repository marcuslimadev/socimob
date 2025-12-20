<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TextFormatterController extends Controller
{
    /**
     * Formatar descrição de imóvel usando OpenAI
     * 
     * POST /api/format-text
     */
    public function formatText(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'text' => 'required|string|max:2000'
            ]);

            $texto = $request->input('text');
            
            // Configurar OpenAI
            $apiKey = env('OPENAI_API_KEY');
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'OpenAI API key não configurada'
                ], 500);
            }

            $prompt = "Você é um especialista em marketing imobiliário. Formate este texto de descrição de imóvel de forma profissional, atrativa e organizada. Mantenha todas as informações importantes, mas torne-a mais vendável e bem estruturada. Use emojis apropriados e organize em tópicos quando necessário. Texto original:\n\n" . $texto;

            $data = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em marketing imobiliário. Sua função é transformar descrições de imóveis em textos atraentes, bem formatados e profissionais.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                \Log::error('OpenAI API Error', [
                    'http_code' => $httpCode,
                    'response' => $response
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Erro ao comunicar com OpenAI'
                ], 500);
            }

            $result = json_decode($response, true);
            
            if (!isset($result['choices'][0]['message']['content'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Resposta inválida da OpenAI'
                ], 500);
            }

            $textoFormatado = trim($result['choices'][0]['message']['content']);

            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $texto,
                    'formatted' => $textoFormatado
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao formatar texto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro interno do servidor'
            ], 500);
        }
    }
}