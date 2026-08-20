<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

Auth::requireAdmin();

$path = trim((string) ($_GET['path'] ?? $_POST['path'] ?? ''));
$page = $path !== '' ? find_page($path) : null;

if (!$page) {
    http_response_code(404);
    render_layout('Not found', function () {
        echo '<div class="panel empty-state">Page not found.</div>';
    });
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'Invalid form submission, please try again.';
        header('Location: ' . page_url($page['path']));
        exit;
    }

    unlink($page['file']);

    $_SESSION['flash_success'] = 'Page deleted.' . (ContentRepo::isEnabled() ? ' Syncing to GitHub in the background.' : '');
    header('Location: /index.php');
    $identity = Auth::gitIdentity();
    ContentRepo::commitAndPushAsync(
        [$page['path'] . '.md'],
        'Delete page: ' . $page['title'],
        $identity['name'] ?? null,
        $identity['email'] ?? null
    );
    exit;
}

$pageTitle = 'Delete: ' . $page['title'];

render_layout($pageTitle, function () use ($page) {
    ?>
    <h2 class="section-heading">Confirm Delete</h2>
    <div class="panel">
        <p>Are you sure you want to delete <strong><?= e($page['title']) ?></strong>? This cannot be undone.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="path" value="<?= e($page['path']) ?>">
            <div class="actions">
                <button type="submit" class="btn-danger" style="background: var(--danger); color:#fff; border-color: var(--danger);">Delete Page</button>
                <a class="btn btn-secondary" href="<?= e(page_url($page['path'])) ?>">Cancel</a>
            </div>
        </form>
    </div>
    <?php
});
