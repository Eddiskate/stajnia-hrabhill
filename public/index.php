<?php

use App\Kernel;

if (\PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH);
    if (\is_string($path) && $path !== '/' && is_file(__DIR__.$path)) {
        return false;
    }
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
