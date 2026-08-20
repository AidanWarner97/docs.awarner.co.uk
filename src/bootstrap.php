<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/ContentRepo.php';

// Auto-provision/upgrade the schema so deployment is just "upload and go".
Database::get()->exec((string) file_get_contents(__DIR__ . '/schema.sql'));

// Add columns introduced after initial release to any pre-existing database.
$existingColumns = array_column(Database::get()->query('PRAGMA table_info(users)')->fetchAll(), 'name');
foreach (['git_name' => '', 'git_email' => ''] as $column => $default) {
    if (!in_array($column, $existingColumns, true)) {
        Database::get()->exec("ALTER TABLE users ADD COLUMN {$column} TEXT NOT NULL DEFAULT ''");
    }
}

foreach ([CONTENT_DIR, UPLOAD_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

$hasUsers = (int) Database::get()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
if (!$hasUsers && basename($_SERVER['SCRIPT_NAME']) !== 'setup.php') {
    header('Location: /setup.php');
    exit;
}
