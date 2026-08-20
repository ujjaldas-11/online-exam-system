<?php
$placeholder = isset($search_placeholder) ? $search_placeholder : "Type to search...";
?>

<link rel="stylesheet" href="../assets/css/components.css">


<div class="search-wrapper no-print">
    <span class="search-icon">🔍</span>
    <input type="text" id="globalTableSearch" class="search-input" placeholder="<?= htmlspecialchars($placeholder) ?>">
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const searchInput = document.getElementById('globalTableSearch');
        if (!searchInput) return;

        // Generic debounce helper: delays calling fn until `delay` ms
        // have passed since the last time it was invoked.
        function debounce(fn, delay) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        function filterTableRows() {
            const filter = searchInput.value.toLowerCase();
            // Find the table body on whatever page this is included in
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
