<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AudioConverterController extends Controller
{
    private const FFMPEG_PATH = 'C:\\ffmpeg\\bin\\ffmpeg.exe';
    
    /**
     * Converter áudio OGG/outros formatos para MP3
     */
    public function convert(Request $request)
    {
        Log::info('🎵 Conversor de áudio chamado', [
            'method' => $request->method(),
            'has_file' => $request->hasFile('audio')
        ]);
        
        if (!$request->hasFile('audio')) {
            Log::error('❌ Nenhum arquivo de áudio enviado');
            return response('Envie o arquivo de áudio em um POST multipart (campo: audio)', 400);
        }
        
        $audioFile = $request->file('audio');
        
        Log::info('📁 Arquivo recebido', [
            'name' => $audioFile->getClientOriginalName(),
            'type' => $audioFile->getMimeType(),
            'size' => $audioFile->getSize()
        ]);
        
        // Salvar arquivo temporário de entrada
        $ext = $audioFile->getClientOriginalExtension() ?: 'ogg';
        $inputFile = sys_get_temp_dir() . '/' . uniqid('audio_', true) . '.' . $ext;
        $outputFile = sys_get_temp_dir() . '/' . uniqid('audio_mp3_', true) . '.mp3';
        
        try {
            // Mover arquivo enviado
            $audioFile->move(dirname($inputFile), basename($inputFile));
            
            Log::info('💾 Arquivo temporário salvo', ['path' => $inputFile]);
            
            // Verificar se FFmpeg existe
            if (!file_exists(self::FFMPEG_PATH)) {
                Log::error('❌ FFmpeg não encontrado', ['path' => self::FFMPEG_PATH]);
                @unlink($inputFile);
                return response('FFmpeg não instalado no servidor', 500);
            }
            
            // Converter com FFmpeg
            $cmd = self::FFMPEG_PATH . " -y -i " . escapeshellarg($inputFile) . " -ar 44100 -ac 2 -b:a 192k " . escapeshellarg($outputFile) . " 2>&1";
            
            Log::info('🔄 Executando conversão', ['cmd' => $cmd]);
            
            exec($cmd, $output, $ret);
            
            Log::info('✅ FFmpeg executado', [
                'return_code' => $ret,
                'output_lines' => count($output)
            ]);
            
            // Remover arquivo de entrada
            @unlink($inputFile);
            
            // Verificar se MP3 foi gerado
            if (!file_exists($outputFile) || filesize($outputFile) === 0) {
                Log::error('❌ Falha na conversão para MP3', [
                    'file_exists' => file_exists($outputFile),
                    'ffmpeg_output' => implode("\n", $output)
                ]);
                return response('Erro na conversão com ffmpeg: ' . implode("\n", $output), 500);
            }
            
            $fileSize = filesize($outputFile);
            Log::info('🎵 MP3 gerado com sucesso', [
                'path' => $outputFile,
                'size' => $fileSize
            ]);
            
            // Ler conteúdo do arquivo
            $mp3Content = file_get_contents($outputFile);
            
            // Remover arquivo temporário
            @unlink($outputFile);
            
            // Retornar MP3
            return response($mp3Content, 200, [
                'Content-Type' => 'audio/mpeg',
                'Content-Disposition' => 'attachment; filename="audio.mp3"',
                'Content-Length' => strlen($mp3Content)
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Erro no conversor de áudio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Cleanup
            @unlink($inputFile);
            @unlink($outputFile);
            
            return response('Erro ao converter áudio: ' . $e->getMessage(), 500);
        }
    }
}
