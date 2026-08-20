<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

// Streams images out of content/uploads/, which lives outside the web root
// alongside the Markdown files so both can be backed up/moved together.

$file = (string) ($_GET['file'] ?? '');

// Only allow the same charset produced by upload.php: YYYY/MM/hexname.ext
if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $file)) {
    http_response_code(404);
    exit;
}

$absPath = UPLOAD_DIR . '/' . $file;
$real = realpath($absPath);
$realRoot = realpath(UPLOAD_DIR);

if ($real === false || $realRoot === false || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR) || !is_file($real)) {
    http_response_code(404);
    exit;
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));

$mtime = (int) filemtime($real);
$etag = '"' . md5($real . $mtime) . '"';

header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($real));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if ($ifNoneMatch === $etag) {
    http_response_code(304);
    exit;
}

readfile($real);
