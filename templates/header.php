<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? SITE_NAME;
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <h1><a href="/index.php"><?= e(SITE_NAME) ?></a></h1>
    <p class="tagline"><?= e(SITE_TAGLINE) ?></p>
</header>
<div class="accent-line"></div>
<nav class="site-nav">
    <a href="/index.php" class="nav-btn">Home</a>
    <?php if ($user): ?>
        <a href="/edit.php" class="nav-btn">New Page</a>
        <a href="/profile.php" class="nav-btn">Profile</a>
        <?php if (Auth::isAdmin()): ?>
            <a href="/users.php" class="nav-btn">Users</a>
        <?php endif; ?>
        <a href="/logout.php" class="nav-btn">Logout (<?= e($user['username']) ?>)</a>
    <?php else: ?>
        <a href="/login.php" class="nav-btn">Login</a>
    <?php endif; ?>
</nav>
<div class="layout">
<?php require APP_ROOT . '/templates/sidebar.php'; ?>
<main class="container">
<?php if ($user): ?>
    <?php $pushStatus = ContentRepo::lastPushStatus(); ?>
    <?php if ($pushStatus !== null && $pushStatus['ok'] === false): ?>
        <div class="flash flash-error">
            Last GitHub sync failed (<?= e(date('Y-m-d H:i', (int) $pushStatus['time'])) ?>):
            <?= e((string) $pushStatus['message']) ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php foreach (['success', 'error'] as $flashType): ?>
    <?php if (!empty($_SESSION['flash_' . $flashType])): ?>
        <div class="flash flash-<?= $flashType ?>"><?= e($_SESSION['flash_' . $flashType]) ?></div>
        <?php unset($_SESSION['flash_' . $flashType]); ?>
    <?php endif; ?>
<?php endforeach; ?>
