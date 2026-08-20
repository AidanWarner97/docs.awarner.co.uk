<?php
declare(strict_types=1);

// Copy this file to content-repo.php (git-ignored) and fill in your details
// to enable syncing content/ from a GitHub repo via bin/sync-content.php.
return [
    // e.g. 'https://github.com/yourname/docs-content.git'
    // For private repos, either embed a fine-scoped token in the URL
    // ('https://TOKEN@github.com/yourname/docs-content.git') or use an
    // SSH URL with a deploy key configured for the user running the cron job.
    // Note: to allow edits made via the web editor to be pushed back
    // (see src/ContentRepo.php), the token/deploy key needs WRITE access.
    'url' => '',
    'branch' => 'main',

    // Used as the git commit author for changes made through the web editor.
    'author_name' => 'Docs Wiki',
    'author_email' => 'docs-wiki@localhost',
];
