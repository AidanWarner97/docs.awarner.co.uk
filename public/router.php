<?php
declare(strict_types=1);

// Router for PHP's built-in dev server, mirroring the pretty-URL rewrite
// rules used in production (see public/.htaccess and the Nginx example in
// README.md). Run with: php -S localhost:8080 -t public public/router.php

$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false; // serve the requested file (assets, php scripts, etc.) as-is
}

if (preg_match('#^/([^/]+)/([^/]+)/?$#', $uri, $m)) {
    $_GET['path'] = $m[1] . '/' . $m[2];
    require __DIR__ . '/page.php';
    return true;
}

require __DIR__ . '/index.php';
