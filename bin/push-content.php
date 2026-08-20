#!/usr/bin/env php
<?php
declare(strict_types=1);

// Spawned in the background by ContentRepo::commitAndPushAsync() so a save
// in the web UI doesn't have to wait for the git commit/push to finish.

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/ContentRepo.php';

$jobFile = $argv[1] ?? null;
if ($jobFile === null || !is_file($jobFile)) {
    fwrite(STDERR, "Usage: push-content.php <job-file>\n");
    exit(1);
}

$job = json_decode((string) file_get_contents($jobFile), true);
@unlink($jobFile);

if (!is_array($job) || empty($job['paths'])) {
    exit(1);
}

$error = ContentRepo::commitAndPush(
    $job['paths'],
    (string) ($job['message'] ?? 'Update content'),
    $job['author_name'] ?? null,
    $job['author_email'] ?? null
);
ContentRepo::recordPushStatus($error);
exit($error === null ? 0 : 1);
