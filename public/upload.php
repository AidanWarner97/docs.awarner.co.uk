<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

Auth::requireLogin();

$returnTo = (string) ($_POST['return_to'] ?? '/edit.php');
// Only allow redirecting back within this app.
if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
    $returnTo = '/edit.php';
}

function fail(string $message, string $returnTo): void
{
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $returnTo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request.', $returnTo);
}

if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    fail('Invalid form submission, please try again.', $returnTo);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    fail('Please choose an image file to upload.', $returnTo);
}

$upload = $_FILES['image'];

$maxBytes = 8 * 1024 * 1024;
if ($upload['size'] > $maxBytes) {
    fail('Image is too large (max 8MB).', $returnTo);
}

// Verify it's a genuine image (not just a renamed file) and detect its real type.
$imageInfo = @getimagesize($upload['tmp_name']);
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo !== false ? finfo_file($finfo, $upload['tmp_name']) : false;
if ($finfo !== false) {
    finfo_close($finfo);
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if ($imageInfo === false || $mime === false || !isset($allowed[$mime])) {
    fail('Unsupported or invalid image file.', $returnTo);
}

$extension = $allowed[$mime];
$subDir = date('Y/m');
$targetDir = UPLOAD_DIR . '/' . $subDir;
if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    fail('Could not save the upload, please try again.', $returnTo);
}

$filename = bin2hex(random_bytes(16)) . '.' . $extension;
$targetPath = $targetDir . '/' . $filename;

$saved = false;

// Re-encode via GD when available: strips any embedded payload/metadata (defense in depth).
if (function_exists('imagecreatefromstring')) {
    $data = file_get_contents($upload['tmp_name']);
    $image = $data !== false ? @imagecreatefromstring($data) : false;
    if ($image !== false) {
        $saved = match ($extension) {
            'jpg' => imagejpeg($image, $targetPath, 88),
            'png' => imagepng($image, $targetPath),
            'gif' => imagegif($image, $targetPath),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $targetPath, 88) : false,
            default => false,
        };
        imagedestroy($image);
    }
}

if (!$saved) {
    $saved = move_uploaded_file($upload['tmp_name'], $targetPath);
}

if (!$saved) {
    fail('Could not save the upload, please try again.', $returnTo);
}

$relativePath = 'uploads/' . $subDir . '/' . $filename;

$publicUrl = upload_url($subDir . '/' . $filename);
$_SESSION['flash_success'] = 'Image uploaded. Markdown snippet: ![alt text](' . $publicUrl . ')'
    . (ContentRepo::isEnabled() ? ' Syncing to GitHub in the background.' : '');
header('Location: ' . $returnTo);
$identity = Auth::gitIdentity();
ContentRepo::commitAndPushAsync(
    [$relativePath],
    'Add image: ' . $subDir . '/' . $filename,
    $identity['name'] ?? null,
    $identity['email'] ?? null
);
exit;
