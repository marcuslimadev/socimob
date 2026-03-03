<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

try {
    // Configurar para usar a fila
    config(['mail.default' => 'smtp']);
    
    echo "📧 Enviando email de teste...\n";
    echo "De: " . env('MAIL_FROM_ADDRESS') . "\n";
    echo "Para: alert@socimob.com\n";
    echo "Host SMTP: " . env('MAIL_HOST') . ":" . env('MAIL_PORT') . "\n\n";
    
    Mail::send([], [], function ($message) {
        $message->to('alert@socimob.com')
                ->subject('Teste de Email - Sistema de Filas SOCIMOB')
                ->html('<h1>Teste de Email</h1><p>Este é um email de teste do sistema de filas do SOCIMOB.</p><p>Hora do envio: ' . date('Y-m-d H:i:s') . '</p>');
    });
    
    echo "✅ Email adicionado à fila com sucesso!\n";
    echo "O worker processará o email em segundo plano.\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao enviar email: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
