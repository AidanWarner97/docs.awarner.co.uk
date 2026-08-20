<?php
declare(strict_types=1);

// Wraps git so edits made through the web UI (create/update/delete pages,
// image uploads) are committed and pushed back to the configured GitHub repo
// automatically - a two-way version of the one-way sync in bin/sync-content.php.
// Shares the same lock file with that script so a cron pull can never race
// with a web-triggered commit/push.
final class ContentRepo
{
    private static ?array $config = null;
    private static bool $configLoaded = false;

    private static function config(): ?array
    {
        if (!self::$configLoaded) {
            self::$configLoaded = true;
            if (is_file(CONTENT_REPO_CONFIG)) {
                $config = require CONTENT_REPO_CONFIG;
                if (is_array($config) && !empty($config['url'])) {
                    self::$config = $config;
                }
            }
        }

        return self::$config;
    }

    public static function isEnabled(): bool
    {
        return self::config() !== null && is_dir(CONTENT_DIR . '/.git');
    }

    private static function run(array $args): array
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(array_merge(['git'], $args), $descriptors, $pipes, CONTENT_DIR);
        if (!is_resource($process)) {
            return [1, '', 'Failed to start git process'];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [$exitCode, trim((string) $stdout), trim((string) $stderr)];
    }

    // Runs $fn while holding the shared content-repo lock, so this never
    // overlaps with bin/sync-content.php pulling in another process.
    private static function withLock(callable $fn)
    {
        $handle = fopen(CONTENT_REPO_LOCK, 'c');
        if ($handle === false) {
            return $fn();
        }

        try {
            flock($handle, LOCK_EX);
            return $fn();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // Commits and pushes changes to the given paths (relative to CONTENT_DIR),
    // covering additions, edits and deletions. $authorName/$authorEmail let a
    // specific user's identity be used instead of the config defaults, so
    // commits are attributed to whoever actually made the edit. Returns null
    // on success, or an error message on failure. The local file change is
    // kept either way - this only affects whether it made it to GitHub.
    public static function commitAndPush(
        array $relativePaths,
        string $message,
        ?string $authorName = null,
        ?string $authorEmail = null
    ): ?string {
        $config = self::config();
        if ($config === null || !is_dir(CONTENT_DIR . '/.git') || empty($relativePaths)) {
            if ($config === null) {
                app_log('content-push.log', 'Skipped: src/content-repo.php missing or has no url.');
            } elseif (!is_dir(CONTENT_DIR . '/.git')) {
                app_log('content-push.log', 'Skipped: content/ has no .git yet - run bin/sync-content.php once first.');
            }
            return null;
        }

        return self::withLock(function () use ($config, $relativePaths, $message, $authorName, $authorEmail) {
            $branch = (string) ($config['branch'] ?? 'main');
            $authorName = $authorName ?? (string) ($config['author_name'] ?? 'Docs Wiki');
            $authorEmail = $authorEmail ?? (string) ($config['author_email'] ?? 'docs-wiki@localhost');

            app_log('content-push.log', "Push requested: {$message} (" . implode(', ', $relativePaths) . ')');

            // Keep the remote in sync with config, in case it was changed since the last run.
            [, $remotes] = self::run(['remote']);
            $hasOrigin = in_array('origin', preg_split('/\s+/', $remotes) ?: [], true);
            self::run($hasOrigin ? ['remote', 'set-url', 'origin', (string) $config['url']] : ['remote', 'add', 'origin', (string) $config['url']]);

            // Catch up with the remote first to minimize the chance of a rejected push.
            self::run(['fetch', '--depth', '1', 'origin', $branch]);
            self::run(['pull', '--rebase', '--autostash', 'origin', $branch]);

            [, $status] = self::run(array_merge(['status', '--porcelain', '--'], $relativePaths));
            if ($status === '') {
                app_log('content-push.log', 'Nothing to push (no changes detected).');
                return null; // nothing actually changed
            }

            self::run(array_merge(['add', '-A', '--'], $relativePaths));

            [$code, , $err] = self::run([
                '-c', 'user.name=' . $authorName,
                '-c', 'user.email=' . $authorEmail,
                'commit', '-m', $message,
            ]);
            if ($code !== 0) {
                app_log('content-push.log', 'Commit failed: ' . $err);
                return 'Could not commit the change locally: ' . $err;
            }

            [$code, , $err] = self::run(['push', 'origin', 'HEAD:' . $branch]);
            if ($code !== 0) {
                app_log('content-push.log', 'Push failed: ' . $err);
                return 'Change saved, but the push to GitHub failed: ' . $err;
            }

            app_log('content-push.log', 'Pushed successfully.');
            return null;
        });
    }

    // Same as commitAndPush(), but doesn't make the caller wait for git.
    // Prefers finishing the HTTP response first (FPM), falls back to a
    // detached CLI process, and only blocks the request as a last resort.
    public static function commitAndPushAsync(
        array $relativePaths,
        string $message,
        ?string $authorName = null,
        ?string $authorEmail = null
    ): void {
        if (self::config() === null || !is_dir(CONTENT_DIR . '/.git') || empty($relativePaths)) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close(); // release the session lock before we go do slow work
            }
            if (!headers_sent()) {
                fastcgi_finish_request(); // client gets its response now; we keep running
            }
            self::recordPushStatus(self::commitAndPush($relativePaths, $message, $authorName, $authorEmail));
            return;
        }

        if (self::canSpawnProcess()) {
            self::spawnDetachedPush($relativePaths, $message, $authorName, $authorEmail);
            return;
        }

        // No way to background it on this setup - fall back to blocking.
        self::recordPushStatus(self::commitAndPush($relativePaths, $message, $authorName, $authorEmail));
    }

    private static function canSpawnProcess(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('exec') && !in_array('exec', $disabled, true);
    }

    private static function spawnDetachedPush(
        array $relativePaths,
        string $message,
        ?string $authorName = null,
        ?string $authorEmail = null
    ): void {
        $jobFile = tempnam(sys_get_temp_dir(), 'docs-push-');
        file_put_contents($jobFile, json_encode([
            'paths' => $relativePaths,
            'message' => $message,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
        ]));

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $script = APP_ROOT . '/bin/push-content.php';

        // Trailing "&" backgrounds the job in the shell, so exec() returns
        // immediately instead of waiting for the push to finish.
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobFile)
            . ' > /dev/null 2>&1 &';

        exec($cmd);
    }

    // Persists the outcome of the last push attempt so the UI can surface a
    // failure on the next page load (there's no request left to flash it to).
    public static function recordPushStatus(?string $error): void
    {
        $status = ['ok' => $error === null, 'message' => $error, 'time' => time()];
        file_put_contents(DATA_DIR . '/content-repo-status.json', json_encode($status));
    }

    public static function lastPushStatus(): ?array
    {
        $file = DATA_DIR . '/content-repo-status.json';
        if (!is_file($file)) {
            return null;
        }

        $status = json_decode((string) file_get_contents($file), true);

        return is_array($status) ? $status : null;
    }
}
