<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

Auth::requireAdmin();

$errors = [];
$currentUser = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission, please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'create') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';
            $gitName = trim((string) ($_POST['git_name'] ?? ''));
            $gitEmail = trim((string) ($_POST['git_email'] ?? ''));

            if (strlen($username) < 3) {
                $errors[] = 'Username must be at least 3 characters.';
            }
            if (strlen($password) < 10) {
                $errors[] = 'Password must be at least 10 characters.';
            }
            if ($gitEmail !== '' && !filter_var($gitEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid GitHub email address, or leave it blank.';
            }

            if (empty($errors)) {
                try {
                    Database::get()->prepare('INSERT INTO users (username, password_hash, role, git_name, git_email) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $gitName, $gitEmail]);
                    $_SESSION['flash_success'] = 'User created.';
                    header('Location: /users.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'That username is already taken.';
                }
            }
        } elseif ($action === 'delete') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) $currentUser['id']) {
                $errors[] = 'You cannot delete your own account.';
            } else {
                Database::get()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
                $_SESSION['flash_success'] = 'User deleted.';
                header('Location: /users.php');
                exit;
            }
        }
    }
}

$users = Database::get()->query('SELECT id, username, role, git_name, git_email, created_at FROM users ORDER BY username')->fetchAll();
$pageTitle = 'Manage Users';

render_layout($pageTitle, function () use ($errors, $users, $currentUser) {
    ?>
    <h2 class="section-heading">Manage Users</h2>

    <?php foreach ($errors as $error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <div class="panel">
        <ul class="page-list">
            <?php foreach ($users as $u): ?>
                <li style="display:flex; justify-content:space-between; align-items:center;">
                    <span>
                        <strong><?= e($u['username']) ?></strong> &middot; <?= e($u['role']) ?>
                        <?php if ($u['git_name'] !== '' || $u['git_email'] !== ''): ?>
                            <span class="meta">&middot; commits as <?= e($u['git_name'] !== '' ? $u['git_name'] : $u['username']) ?><?= $u['git_email'] !== '' ? ' &lt;' . e($u['git_email']) . '&gt;' : '' ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ((int) $u['id'] !== (int) $currentUser['id']): ?>
                        <form method="post" onsubmit="return confirm('Delete user <?= e($u['username']) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn-danger" style="background:transparent; border-color:var(--danger); color:var(--danger);">Delete</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2 class="section-heading">Add User</h2>
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <input type="hidden" name="action" value="create">

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required minlength="3">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">

            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="editor">Editor</option>
                <option value="admin">Admin</option>
            </select>

            <label for="git_name">GitHub / commit name (optional)</label>
            <input type="text" id="git_name" name="git_name" placeholder="Defaults to username">

            <label for="git_email">GitHub / commit email (optional)</label>
            <input type="text" id="git_email" name="git_email" placeholder="you@example.com">

            <div class="actions">
                <button type="submit">Create User</button>
            </div>
        </form>
    </div>
    <?php
});
