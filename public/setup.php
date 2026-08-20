<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$hasUsers = (int) Database::get()->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'] > 0;
if ($hasUsers) {
    header('Location: /login.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $stmt = Database::get()->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);

            $_SESSION['flash_success'] = 'Admin account created. Please log in.';
            header('Location: /login.php');
            exit;
        }
    }
}

$pageTitle = 'Initial Setup';

render_layout($pageTitle, function () use ($errors) {
    ?>
    <h2 class="section-heading">Create Admin Account</h2>
    <p>No users exist yet. Create the first administrator account to get started.</p>
    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required minlength="3">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required minlength="10">

            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required minlength="10">

            <div class="actions">
                <button type="submit">Create Account</button>
            </div>
        </form>
    </div>
    <?php
});
