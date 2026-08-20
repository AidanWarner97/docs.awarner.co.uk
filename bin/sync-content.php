#!/usr/bin/env php
<?php
declare(strict_types=1);

// Syncs content/ from a GitHub (or any git) repo. Intended to be run on a
// cron schedule, e.g.:
//   */15 * * * * php /path/to/repo/bin/sync-content.php >> /path/to/repo/data/content-sync.log 2>&1
//
// Treats the remote repo as the source of truth for tracked files: existing
// Markdown pages are hard-reset to match origin. Untracked local files
// (uploaded images in content/uploads/, the .htaccess files) are left alone,
// since `git reset --hard` never touches untracked files.

require __DIR__ . '/../src/config.php';

function log_line(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function run_git(array $args, string $cwd): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open(array_merge(['git'], $args), $descriptors, $pipes, $cwd);
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

$configFile = __DIR__ . '/../src/content-repo.php';
if (!is_file($configFile)) {
    log_line('No src/content-repo.php found. Copy src/content-repo.example.php and set your repo URL.');
    exit(1);
}

$config = require $configFile;
$url = trim((string) ($config['url'] ?? ''));
$branch = trim((string) ($config['branch'] ?? 'main')) ?: 'main';

if ($url === '') {
    log_line('src/content-repo.php has no repo URL configured.');
    exit(1);
}

if (!is_dir(CONTENT_DIR) && !mkdir(CONTENT_DIR, 0775, true) && !is_dir(CONTENT_DIR)) {
    log_line('Could not create content directory: ' . CONTENT_DIR);
    exit(1);
}

// Check CONTENT_DIR itself has a .git, not just "is inside a work tree"
// (the latter would say yes if some ancestor directory happens to be a repo
// too, e.g. the project root - and then wrongly reuse/reset *that* repo).
if (!is_dir(CONTENT_DIR . '/.git')) {
    log_line('Initializing git repo in ' . CONTENT_DIR);
    [$code, , $err] = run_git(['init', '-q'], CONTENT_DIR);
    if ($code !== 0) {
        log_line('git init failed: ' . $err);
        exit(1);
    }
}

[, $remotes] = run_git(['remote'], CONTENT_DIR);
$hasOrigin = in_array('origin', preg_split('/\s+/', $remotes) ?: [], true);
[$code, , $err] = run_git($hasOrigin ? ['remote', 'set-url', 'origin', $url] : ['remote', 'add', 'origin', $url], CONTENT_DIR);
if ($code !== 0) {
    log_line('Failed to configure remote: ' . $err);
    exit(1);
}

// Held for the fetch+reset below so this can never race with a web-triggered
// commit/push (see src/ContentRepo.php, which shares this same lock file).
$lock = fopen(CONTENT_REPO_LOCK, 'c');
if ($lock !== false) {
    flock($lock, LOCK_EX);
}

log_line("Fetching '{$branch}' from origin...");
[$code, , $err] = run_git(['fetch', '--depth', '1', 'origin', $branch], CONTENT_DIR);
if ($code !== 0) {
    log_line('git fetch failed: ' . $err);
    exit(1);
}

[$code, $out, $err] = run_git(['reset', '--hard', 'origin/' . $branch], CONTENT_DIR);

if ($lock !== false) {
    flock($lock, LOCK_UN);
    fclose($lock);
}

if ($code !== 0) {
    log_line('git reset failed: ' . $err);
    exit(1);
}

log_line('Content synced successfully: ' . $out);
exit(0);
