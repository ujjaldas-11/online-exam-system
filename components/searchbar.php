<?php 
$placeholder = isset($search_placeholder) ? $search_placeholder : "Type to search..."; 
?>

<style>
.search-wrapper {
        margin-bottom: 20px;
        position: relative;
    }
.search-input {
        width: 100%;
        max-width: 400px;
        padding: 10px 15px 10px 35px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s border-color;
    }
.search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
.search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray);
    }
@media print {
.search-wrapper { display: none !important; }
    }
</style>

<div class="search-wrapper no-print">
    <span class="search-icon">🔍</span>
    <input type="text" id="globalTableSearch" class="search-input" placeholder="<?= htmlspecialchars($placeholder) ?>">
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('globalTableSearch');
    if (!searchInput) return;

    // Generic debounce helper: delays calling fn until `delay` ms
    // have passed since the last time it was invoked.
    function debounce(fn, delay) {
        let timeoutId;
        return function(...args) {
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