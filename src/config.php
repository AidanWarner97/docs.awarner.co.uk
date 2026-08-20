<?php
// Central configuration for the wiki application.
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('DB_PATH', DATA_DIR . '/wiki.sqlite');
define('CONTENT_DIR', APP_ROOT . '/content');
define('UPLOAD_DIR', CONTENT_DIR . '/uploads');
define('CONTENT_REPO_CONFIG', APP_ROOT . '/src/content-repo.php');
define('CONTENT_REPO_LOCK', DATA_DIR . '/content-repo.lock');
define('SITE_NAME', 'Aidan Warner Docs');
define('SITE_TAGLINE', 'Wiki & Documentation');

// Secure session cookie settings must be set before session_start().
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
