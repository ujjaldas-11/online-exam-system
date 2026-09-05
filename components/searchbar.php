<?php
require_once __DIR__ . '/../utils/env.php';

$placeholder = isset($search_placeholder) ? $search_placeholder : "Type to search...";
$assetVersion = asset_version();
?>

<div class="search-wrapper no-print">
    <input type="text" id="globalTableSearch" class="search-input" placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>">
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchInput = document.getElementById('globalTableSearch');
        if (!searchInput) return;

        function debounce(fn, delay) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        function filterTableRows() {
            const filter = searchInput.value.toLowerCase();
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(filter) ? '' : 'none';
            });
        }

        const debouncedFilter = debounce(filterTableRows, 300);
        searchInput.addEventListener('keyup', debouncedFilter);
    });
</script>
