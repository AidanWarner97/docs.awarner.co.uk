<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

Auth::requireLogin();

$user = Auth::user();
$stmt = Database::get()->prepare('SELECT git_name, git_email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$row = $stmt->fetch() ?: ['git_name' => '', 'git_email' => ''];

$gitName = $row['git_name'];
$gitEmail = $row['git_email'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    } else {
        $gitName = trim((string) ($_POST['git_name'] ?? ''));
        $gitEmail = trim((string) ($_POST['git_email'] ?? ''));

        if ($gitEmail !== '' && !filter_var($gitEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address, or leave it blank.';
        }

        if (empty($errors)) {
            Database::get()->prepare('UPDATE users SET git_name = ?, git_email = ? WHERE id = ?')
                ->execute([$gitName, $gitEmail, $user['id']]);

            $_SESSION['flash_success'] = 'Profile updated.';
            header('Location: /profile.php');
            exit;
        }
    }
}

$pageTitle = 'Your Profile';

render_layout($pageTitle, function () use ($errors, $user, $gitName, $gitEmail) {
    ?>
    <h2 class="section-heading">Your Profile</h2>

    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <div class="panel">
        <p class="meta">Signed in as <strong><?= e($user['username']) ?></strong> (<?= e($user['role']) ?>)</p>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">

            <label for="git_name">GitHub / commit name</label>
            <input type="text" id="git_name" name="git_name" value="<?= e($gitName) ?>" placeholder="<?= e($user['username']) ?>">

            <label for="git_email">GitHub / commit email</label>
            <input type="text" id="git_email" name="git_email" value="<?= e($gitEmail) ?>" placeholder="you@example.com">

            <p class="meta">Used as the git author when your edits are pushed to GitHub, so
            commits show your name instead of a generic one. Leave blank to use the site
            default.</p>

            <div class="actions">
                <button type="submit">Save Profile</button>
            </div>
        </form>
    </div>
    <?php
});
