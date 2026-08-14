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
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            // Find the table body on whatever page this is included in
            const tableRows = document.querySelectorAll('tbody tr');

            tableRows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                
                if (rowText.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>