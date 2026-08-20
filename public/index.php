<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$pageTitle = 'Welcome';
$hasPages = !empty(all_pages());

render_layout($pageTitle, function () use ($hasPages) {
    ?>
    <h2 class="section-heading">Welcome</h2>

    <div class="panel">
        <p>This is the documentation &amp; wiki site for Aidan Warner's projects.</p>
        <p>Use the <strong>Contents</strong> tree in the sidebar to browse categories and
        pages. Click a category to expand it and reveal its pages, then select a page to
        read it.</p>
        <?php if (Auth::check()): ?>
            <p>You're logged in, so you can create new pages or edit existing ones
            using the buttons on each page and the "New Page" link in the top navigation.
            You can also add Markdown files directly to the <code>content/</code> folder
            (one sub-folder per category) and they'll show up here automatically.</p>
        <?php else: ?>
            <p>Want to contribute? <a href="/login.php">Log in</a> to create and edit pages.</p>
        <?php endif; ?>

        <?php if (!$hasPages): ?>
            <p class="empty-state">No pages have been created yet.</p>
            <?php if (Auth::check()): ?>
                <p><a class="btn" href="/edit.php">Create the first page</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
});

