<?php
/** @var array|null $grouped */
$grouped = $grouped ?? pages_by_category();
$currentPath = $_GET['path'] ?? null;
?>
<aside class="sidebar">
    <div class="sidebar-title">Contents</div>
    <?php if (empty($grouped)): ?>
        <p class="sidebar-empty">No pages yet.</p>
    <?php else: ?>
        <?php foreach ($grouped as $category => $pages): ?>
            <details class="tree-category" open>
                <summary><?= e(display_category($category)) ?></summary>
                <ul class="tree-pages">
                    <?php foreach ($pages as $p): ?>
                        <li>
                            <a href="<?= e(page_url($p['path'])) ?>"
                               class="<?= $currentPath === $p['path'] ? 'active' : '' ?>">
                                <?= e($p['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</aside>
