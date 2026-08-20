<?php
/** @var array|null $tree */
$tree = $tree ?? build_category_tree();
$currentPath = $_GET['path'] ?? null;
?>
<aside class="sidebar">
    <div class="sidebar-title">Contents</div>
    <?php if (empty($tree)): ?>
        <p class="sidebar-empty">No pages yet.</p>
    <?php else: ?>
        <?php render_sidebar_tree($tree, $currentPath); ?>
    <?php endif; ?>
</aside>
