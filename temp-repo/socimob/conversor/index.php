<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('FFMPEG_PATH', 'C:\\ffmpeg\\bin\\ffmpeg.exe');

function logConversor($msg) {
    file_put_contents(__DIR__ . '/conversor.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

logConversor("==== Nova requisição ====");
logConversor("Método: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['audio'])) {
    logConversor("Requisição inválida ou arquivo não enviado.");
    http_response_code(400);
    echo 'Envie o arquivo de áudio em um POST multipart (campo: audio)';
    exit;
}

logConversor("Arquivo recebido: " . $_FILES['audio']['name'] . " (" . $_FILES['audio']['type'] . "), " . $_FILES['audio']['size'] . " bytes");

$audioFile = $_FILES['audio']['tmp_name'];
$ext = pathinfo($_FILES['audio']['name'], PATHINFO_EXTENSION);
$inputFile = sys_get_temp_dir() . '/' . uniqid('audio_', true) . '.' . $ext;
$outputFile = sys_get_temp_dir() . '/' . uniqid('audio_mp3_', true) . '.mp3';

if (!move_uploaded_file($audioFile, $inputFile)) {
    logConversor("Falha ao mover o arquivo temporário.");
    http_response_code(500);
    echo "Erro ao salvar arquivo temporário.";
    exit;
}

logConversor("Arquivo temporário salvo em: $inputFile");

$cmd = FFMPEG_PATH . " -y -i " . escapeshellarg($inputFile) . " -ar 44100 -ac 2 -b:a 192k " . escapeshellarg($outputFile) . " 2>&1";
logConversor("Comando ffmpeg: $cmd");

exec($cmd, $output, $ret);

logConversor("Saída do ffmpeg: " . implode(" | ", $output));
unlink($inputFile);

if (!file_exists($outputFile)) {
    logConversor("Falha na conversão para mp3.");
    http_response_code(500);
    echo "Erro na conversão com ffmpeg: " . implode("\n", $output);
    exit;
}

logConversor("Arquivo mp3 gerado em: $outputFile (" . filesize($outputFile) . " bytes)");

header('Content-Type: audio/mpeg');
header('Content-Disposition: attachment; filename="audio.mp3"');
header('Content-Length: ' . filesize($outputFile));
readfile($outputFile);
logConversor("Download enviado e arquivo removido.\n");
unlink($outputFile);
exit;
