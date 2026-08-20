<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

Auth::requireLogin();

$path = trim((string) ($_GET['path'] ?? $_POST['original_path'] ?? ''));
$existing = $path !== '' ? find_page($path) : null;
$isNew = $existing === null;

$errors = [];
$title = $existing['title'] ?? '';
$category = $existing !== null ? display_category($existing['category']) : 'General';
$content = $existing['content'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? '')) ?: 'General';
        $content = (string) ($_POST['content'] ?? '');

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if (empty($errors)) {
            $categoryDir = category_folder_name($category);

            if ($isNew) {
                $slug = slugify($title);
                $base = $slug;
                $i = 2;
                while (resolve_page_file($categoryDir, $slug) !== null) {
                    $slug = $base . '-' . $i++;
                }

                $file = resolve_page_file($categoryDir, $slug, false);
                write_page_file($file, $title, $content);

                $_SESSION['flash_success'] = 'Page created.' . (ContentRepo::isEnabled() ? ' Syncing to GitHub in the background.' : '');
                header('Location: ' . page_url($categoryDir . '/' . $slug));
                $identity = Auth::gitIdentity();
                ContentRepo::commitAndPushAsync(
                    [$categoryDir . '/' . $slug . '.md'],
                    'Add page: ' . $title,
                    $identity['name'] ?? null,
                    $identity['email'] ?? null
                );
                exit;
            }

            // Keep the same filename/slug, but allow the category (folder) to change.
            $newFile = resolve_page_file($categoryDir, $existing['slug'], false);
            write_page_file($newFile, $title, $content);

            $changedPaths = [$categoryDir . '/' . $existing['slug'] . '.md'];
            if ($newFile !== $existing['file']) {
                unlink($existing['file']);
                $changedPaths[] = $existing['path'] . '.md';
            }

            $_SESSION['flash_success'] = 'Page updated.' . (ContentRepo::isEnabled() ? ' Syncing to GitHub in the background.' : '');
            header('Location: ' . page_url($categoryDir . '/' . $existing['slug']));
            $identity = Auth::gitIdentity();
            ContentRepo::commitAndPushAsync(
                $changedPaths,
                'Update page: ' . $title,
                $identity['name'] ?? null,
                $identity['email'] ?? null
            );
            exit;
        }
    }
}

$pageTitle = $isNew ? 'New Page' : 'Editing: ' . $title;

render_layout($pageTitle, function () use ($errors, $title, $category, $content, $isNew, $existing) {
    ?>
    <h2 class="section-heading"><?= $isNew ? 'New Page' : 'Edit Page' ?></h2>

    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <div class="panel">
        <form method="post" class="wide">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <?php if (!$isNew): ?>
                <input type="hidden" name="original_path" value="<?= e($existing['path']) ?>">
            <?php endif; ?>

            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($title) ?>" required>

            <label for="category">Category</label>
            <input type="text" id="category" name="category" value="<?= e($category) ?>" placeholder="General">
            <p class="meta">Use "/" for subcategories, e.g. "Evolution X / CDN".</p>

            <label for="content">Content (Markdown)</label>
            <textarea id="content" name="content" placeholder="# Heading&#10;&#10;Write your documentation here using Markdown..."><?= e($content) ?></textarea>

            <div class="actions">
                <button type="submit">Save Page</button>
                <a class="btn btn-secondary" href="<?= $isNew ? '/index.php' : e(page_url($existing['path'])) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <h2 class="section-heading">Upload Image</h2>
    <div class="panel">
        <p class="meta">Upload an image to use in your Markdown content. Once uploaded, copy the
        snippet shown and paste it into the content box above.</p>
        <form method="post" action="/upload.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI']) ?>">
            <label for="image">Image file (JPEG, PNG, GIF or WebP)</label>
            <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/gif,image/webp" required>
            <div class="actions">
                <button type="submit">Upload</button>
            </div>
        </form>
    </div>
    <?php
});
