<?php

// Link simbólico para storage - Windows
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

if (!file_exists($link)) {
    // No Windows, criar junction ao invés de symlink
    if (PHP_OS_FAMILY === 'Windows') {
        exec("mklink /J \"$link\" \"$target\"", $output, $result);
        if ($result === 0) {
            echo "✓ Storage link criado com sucesso!\n";
        } else {
            echo "❌ Erro ao criar link: " . implode("\n", $output) . "\n";
        }
    } else {
        symlink($target, $link);
        echo "✓ Storage link criado!\n";
    }
} else {
    echo "✓ Storage link já existe\n";
}
