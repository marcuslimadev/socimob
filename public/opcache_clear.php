<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache limpo com sucesso!\n";
} else {
    echo "OPcache não habilitado\n";
}

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    echo "Enabled: " . ($status['opcache_enabled'] ? 'yes' : 'no') . "\n";
    echo "Scripts em cache: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
}
