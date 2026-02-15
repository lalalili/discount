<?php

declare(strict_types=1);

if (! defined('Larastan\Larastan\LARAVEL_VERSION')) {
    define('Larastan\Larastan\LARAVEL_VERSION', '12.0.0');
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $basePath = __DIR__;

        return $path === '' ? $basePath : $basePath . DIRECTORY_SEPARATOR . $path;
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        $configPath = base_path('config');

        return $path === '' ? $configPath : $configPath . DIRECTORY_SEPARATOR . $path;
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        $databasePath = base_path('database');

        return $path === '' ? $databasePath : $databasePath . DIRECTORY_SEPARATOR . $path;
    }
}
