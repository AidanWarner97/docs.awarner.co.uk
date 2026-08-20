<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$path = trim((string) ($_GET['path'] ?? ''));
$page = $path !== '' ? find_page($path) : null;

if (!$page) {
    http_response_code(404);
    render_layout('Not found', function () {
        echo '<div class="panel empty-state">Page not found.</div>';
    });
    exit;
}

$pageTitle = $page['title'];

render_layout($pageTitle, function () use ($page) {
    ?>
    <div class="page-header">
        <div>
            <h1><?= e($page['title']) ?></h1>
            <div class="meta">
                <?= e(display_category($page['category'])) ?> &middot; Updated <?= e($page['updated_at']) ?>
            </div>
        </div>
        <?php if (Auth::check()): ?>
            <div class="actions">
                <a class="btn" href="/edit.php?path=<?= urlencode($page['path']) ?>">Edit</a>
                <?php if (Auth::isAdmin()): ?>
                    <a class="btn btn-danger" href="/delete.php?path=<?= urlencode($page['path']) ?>">Delete</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="panel markdown-body">
        <?= render_markdown($page['content'], dirname($page['file'])) ?>
    </div>
    <?php
});
