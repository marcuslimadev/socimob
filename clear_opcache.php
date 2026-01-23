<?php
// Limpar OPcache e outros caches PHP

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache limpo!\n";
} else {
    echo "OPcache não está habilitado\n";
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "APCu limpo!\n";
}

echo "\nCache PHP resetado com sucesso!\n";
