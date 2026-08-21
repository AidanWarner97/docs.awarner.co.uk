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
    </div>
    <?php
});

