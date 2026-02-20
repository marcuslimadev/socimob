<?php

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     * Lumen does not include this helper by default.
     */
    function public_path(string $path = ''): string
    {
        $base = base_path('public');
        return $path ? $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $base;
    }
}
