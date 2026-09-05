<?php
/**
 * Shared Pagination Component
 * Expects variables:
 *   $page        (int) Current page (1-indexed)
 *   $per_page    (int) Items per page
 *   $total_items (int) Total count of records matching criteria
 */
declare(strict_types=1);

$page = max(1, (int)($page ?? 1));
$per_page = max(1, (int)($per_page ?? 25));
$total_items = max(0, (int)($total_items ?? 0));
$total_pages = max(1, (int)ceil($total_items / $per_page));

if ($total_items <= 0) {
    return;
}

$start_item = (($page - 1) * $per_page) + 1;
$end_item = min($total_items, $page * $per_page);

// Helper to build page URL preserving query params
$queryParams = $_GET;
$buildPageUrl = function(int $targetPage) use ($queryParams): string {
    $queryParams['page'] = $targetPage;
    return '?' . http_build_query($queryParams);
};
?>
<div class="pagination-container no-print">
    <div class="pagination-info">
        Showing <strong><?= $start_item ?></strong> to <strong><?= $end_item ?></strong> of <strong><?= $total_items ?></strong> entries
    </div>
    <?php if ($total_pages > 1): ?>
        <nav class="pagination-nav" aria-label="Table pagination">
            <!-- Previous Button -->
            <a href="<?= $page > 1 ? htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') : '#' ?>"
               class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>"
               aria-label="Previous Page" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                <span class="material-symbols-outlined icon-xs">chevron_left</span>
            </a>

            <!-- Page Number Links -->
            <?php
            $window = 2; // Show 2 pages on each side of current page
            $startPage = max(1, $page - $window);
            $endPage = min($total_pages, $page + $window);

            if ($startPage > 1) {
                echo '<a href="' . htmlspecialchars($buildPageUrl(1), ENT_QUOTES, 'UTF-8') . '" class="pagination-btn">1</a>';
                if ($startPage > 2) {
                    echo '<span class="pagination-btn disabled" style="border: none; background: none;">...</span>';
                }
            }

            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
                <a href="<?= htmlspecialchars($buildPageUrl($p), ENT_QUOTES, 'UTF-8') ?>"
                   class="pagination-btn <?= $p === $page ? 'active' : '' ?>"
                   <?= $p === $page ? 'aria-current="page"' : '' ?>>
                    <?= $p ?>
                </a>
            <?php
            endfor;

            if ($endPage < $total_pages) {
                if ($endPage < $total_pages - 1) {
                    echo '<span class="pagination-btn disabled" style="border: none; background: none;">...</span>';
                }
                echo '<a href="' . htmlspecialchars($buildPageUrl($total_pages), ENT_QUOTES, 'UTF-8') . '" class="pagination-btn">' . $total_pages . '</a>';
            }
            ?>

            <!-- Next Button -->
            <a href="<?= $page < $total_pages ? htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') : '#' ?>"
               class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
               aria-label="Next Page" <?= $page >= $total_pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                <span class="material-symbols-outlined icon-xs">chevron_right</span>
            </a>
        </nav>
    <?php endif; ?>
</div>
