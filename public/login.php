<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission, please try again.';
    } else {
        // Basic throttling to slow down brute-force attempts.
        $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
        $_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

        if (time() < $_SESSION['login_locked_until']) {
            $error = 'Too many attempts. Please wait a moment before trying again.';
        } else {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (Auth::attempt($username, $password)) {
                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                header('Location: /index.php');
                exit;
            }

            $_SESSION['login_attempts']++;
            if ($_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_locked_until'] = time() + 30;
                $_SESSION['login_attempts'] = 0;
            }

            $error = 'Invalid username or password.';
        }
    }
}

$pageTitle = 'Login';

render_layout($pageTitle, function () use ($error) {
    ?>
    <h2 class="section-heading">Login</h2>
    <?php if ($error): ?>
        <div class="flash flash-error"><?= e($error) ?></div>
    <?php endif; ?>
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" autocomplete="username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>

            <div class="actions">
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
    <?php
});
