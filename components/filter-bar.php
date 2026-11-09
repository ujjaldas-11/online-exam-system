<?php
/**
 * Global Filter & Search Bar Component (Live Search)
 *
 * Expected configuration variables passed before inclusion:
 * @var string $form_action        (The page URL the form submits to)
 * @var array  $departments        (Optional array of departments for dropdown)
 * @var bool   $show_dept          (Show/Hide department dropdown, default false)
 * @var bool   $show_sem           (Show/Hide semester dropdown, default false)
 * @var bool   $show_status        (Show/Hide status dropdown, default false)
 * @var bool   $show_author        (Show/Hide author dropdown, default false)
 * @var string $search_placeholder (Placeholder text for the search box)
 * @var string $live_target        (CSS selector of the element wrapping your results
 *                                  table + pagination. Default: '#live-results')
 * @var int    $live_delay         (Debounce delay in ms. Default: 400)
 * @var int    $live_min_chars     (Min characters before searching. Default: 2.
 *                                  Clearing the box always refreshes the list.)
 */

// --- Defaults (must come BEFORE they are used) ---
$form_action        = $form_action ?? '#';
$show_dept          = $show_dept ?? false;
$show_sem           = $show_sem ?? false;
$show_status        = $show_status ?? false;
$show_author        = $show_author ?? false;
$show_year          = $show_year ?? false;
$show_admin         = $show_admin ?? false;
$show_action        = $show_action ?? false;
$inline_filters     = $inline_filters ?? false;
$search_placeholder = $search_placeholder ?? "Search records...";
$departments        = $departments ?? [];
$status_list        = $status_list ?? [];
$authors_list       = $authors_list ?? [];
$admin_list         = $admin_list ?? [];
$action_list        = $action_list ?? [];
$live_target        = $live_target ?? '#live-results';
$live_delay         = (int)($live_delay ?? 400);
$live_min_chars     = (int)($live_min_chars ?? 2);

$managed_keys = ['q', 'department', 'semester', 'status', 'author', 'admin_id', 'action', 'year', 'page'];

// --- Years list setup (defaults to current year down to recent years) ---
$current_year = (int)date('Y');
$years_list   = $years_list ?? [];
if (empty($years_list)) {
    $install_year = 2026; // The exact year Examify was deployed
    $max_year = $current_year;
    $min_year = $install_year; 
    if (isset($_GET['year']) && is_numeric($_GET['year'])) {
        $min_year = min($min_year, (int)$_GET['year']);
        $max_year = max($max_year, (int)$_GET['year']);
    }
    $years_list = range($max_year, $min_year);
}

// --- Build a Smart Reset URL ---
$reset_params = [];
foreach ($_GET as $key => $val) {
    if (is_scalar($val) && !in_array($key, $managed_keys, true)) {
        $reset_params[$key] = $val;
    }
}
$reset_query_string = http_build_query($reset_params);
$reset_url = $form_action . ($reset_query_string ? '?' . $reset_query_string : '');

// Retain existing filters from GET request safely
$filterQ       = is_scalar($_GET['q'] ?? '') ? (string)($_GET['q'] ?? '') : '';
$filterDept    = $_GET['department'] ?? '';
$filterSem     = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$filterStatus  = $_GET['status'] ?? '';
$filterAuthor  = $_GET['author'] ?? '';
$filterAdminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$filterAction  = is_scalar($_GET['action'] ?? '') ? (string)($_GET['action'] ?? '') : '';
$filterYear    = $filterYear ?? (isset($_GET['year']) && $_GET['year'] === 'all' ? 'all' : (isset($_GET['year']) && is_numeric($_GET['year']) && (int)$_GET['year'] > 0 ? (int)$_GET['year'] : $current_year));

// Active Filters Count for the Badge
$active_filters_count = 0;
if (!empty($filterDept))     $active_filters_count++;
if (!empty($filterSem))      $active_filters_count++;
if (!empty($filterStatus))   $active_filters_count++;
if (!empty($filterAuthor))   $active_filters_count++;
if (!$inline_filters) {
    if (!empty($filterAdminId)) $active_filters_count++;
    if (!empty($filterAction))  $active_filters_count++;
}

$has_advanced_filters = (!$inline_filters && ($show_dept || $show_sem || $show_status || $show_author || $show_admin || $show_action));
?>

<style>
    .modern-search-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    .modern-search-wrapper .icon-search {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 20px;
        pointer-events: none;
    }
    .modern-search-input {
        padding-left: 42px !important;
        padding-right: 40px !important;
        border-radius: 8px;
        width: 100%;
    }

    .modern-year-wrapper {
        position: relative;
        display: inline-flex;
        align-items: center;
    }
    .modern-year-wrapper .icon-calendar {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 18px;
        pointer-events: none;
    }
    .modern-year-select {
        height: 38px;
        padding-left: 32px !important;
        padding-right: 28px !important;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        width: auto;
        min-width: 105px;
    }
    .modern-inline-select {
        height: 38px;
        border-radius: 8px;
        font-size: 0.9rem;
        cursor: pointer;
        width: auto;
    }

    /* Spinner shown inside the search box while fetching */
    .search-spinner {
        position: absolute;
        right: 14px;
        top: 50%;
        width: 16px;
        height: 16px;
        margin-top: -8px;
        border: 2px solid var(--color-border, #ddd);
        border-top-color: var(--color-primary, #4f46e5);
        border-radius: 50%;
        animation: search-spin 0.7s linear infinite;
        display: none;
        pointer-events: none;
    }
    .modern-search-wrapper.is-loading .search-spinner { display: block; }
    @keyframes search-spin { to { transform: rotate(360deg); } }

    /* Dim results while a request is in flight */
    .live-loading {
        opacity: 0.5;
        transition: opacity 0.15s ease;
        pointer-events: none;
    }

    /* Modal Overlay */
    .filter-modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.4);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .filter-modal-overlay.active {
        display: flex;
        opacity: 1;
    }

    .filter-modal-content {
        background: var(--color-bg);
        width: 100%;
        max-width: 400px;
        border-radius: var(--radius-md);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        padding: 24px;
        transform: translateY(20px);
        transition: transform 0.2s ease;
        margin: 0 16px;
    }
    .filter-modal-overlay.active .filter-modal-content {
        transform: translateY(0);
    }

    .filter-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--color-primary);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.65rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--color-bg);
    }
</style>

<div style="margin-bottom: 16px;">
    <form method="GET" id="globalFilterForm" action="<?= htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') ?>"
          data-live-target="<?= htmlspecialchars($live_target, ENT_QUOTES, 'UTF-8') ?>"
          data-live-delay="<?= $live_delay ?>"
          data-live-min="<?= $live_min_chars ?>">

        <!-- Retain Hidden Params -->
        <?php foreach ($_GET as $key => $val): ?>
            <?php if (is_scalar($val) && !in_array($key, $managed_keys, true)): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Primary Top Bar -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">

            <!-- Search Input -->
            <div class="modern-search-wrapper" id="searchWrapper">
                <span class="material-symbols-outlined icon-search">search</span>
                <input type="search" id="liveSearchInput" name="q" value="<?= e($filterQ) ?>"
                       placeholder="<?= e($search_placeholder) ?>"
                       class="form-control modern-search-input"
                       autocomplete="off" spellcheck="false">
                <span class="search-spinner" aria-hidden="true"></span>
            </div>

            <!-- Year Filter Select -->
            <?php if ($show_year): ?>
                <div class="modern-year-wrapper">
                    <span class="material-symbols-outlined icon-calendar">calendar_today</span>
                    <select name="year" id="filterYearSelect" class="form-control modern-year-select" aria-label="Filter by Year" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                        <?php foreach ($years_list as $yr): ?>
                            <option value="<?= (int)$yr ?>" <?= (string)$filterYear === (string)$yr ? 'selected' : '' ?>>
                                <?= (int)$yr ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="all" <?= (string)$filterYear === 'all' ? 'selected' : '' ?>>All Years</option>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Inline Filters (used if inline_filters is true, e.g. audit-logs.php) -->
            <?php if ($inline_filters): ?>
                <?php if ($show_admin && !empty($admin_list)): ?>
                    <select name="admin_id" class="form-control modern-inline-select" aria-label="Filter by Instructor" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                        <option value="0">All Instructors &amp; Admins</option>
                        <?php foreach ($admin_list as $adm): ?>
                            <option value="<?= (int)$adm['id'] ?>" <?= (int)$filterAdminId === (int)$adm['id'] ? 'selected' : '' ?>>
                                <?= e($adm['name']) ?> (<?= ucfirst(e($adm['role'])) ?><?= ($adm['status'] ?? '') === 'retired' ? ', Retired' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <?php if ($show_action && !empty($action_list)): ?>
                    <select name="action" class="form-control modern-inline-select" aria-label="Filter by Action" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                        <option value="">All Actions</option>
                        <?php foreach ($action_list as $act): ?>
                            <option value="<?= e($act) ?>" <?= (string)$filterAction === (string)$act ? 'selected' : '' ?>>
                                <?= e(ucwords(str_replace('_', ' ', $act))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Open Filters Modal Button -->
            <?php if ($has_advanced_filters): ?>
                <button type="button" class="btn btn-secondary" onclick="toggleFilterModal()" style="position: relative; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                    <span class="material-symbols-outlined icon-sm">tune</span> Filters
                    <?php if ($active_filters_count > 0): ?>
                        <span class="filter-badge" id="filterBadge"><?= $active_filters_count ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <!-- Reset Button -->
            <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="height: 38px; display: inline-flex; align-items: center; gap: 4px;" title="Reset filters" global-data-tooltip="reset filter">
                <span class="material-symbols-outlined icon-sm">restart_alt</span>
            </a>

        </div>

        <!-- Advanced Filters Modal -->
        <?php if ($has_advanced_filters): ?>
        <div id="filterModal" class="filter-modal-overlay" onclick="handleModalClick(event)">
            <div class="filter-modal-content">

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; color: var(--color-dark);">
                        <span class="material-symbols-outlined">filter_alt</span> Advanced Filters
                    </h3>
                    <button type="button" onclick="toggleFilterModal()" style="background: transparent; border: none; cursor: pointer; color: var(--color-text-secondary); display: flex; align-items: center;">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div style="display: grid; gap: 16px; margin-bottom: 24px;">

                    <?php if (!$inline_filters && $show_admin && !empty($admin_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Instructor / Admin</label>
                            <select name="admin_id" class="form-control" style="width: 100%;">
                                <option value="0">All Instructors &amp; Admins</option>
                                <?php foreach ($admin_list as $adm): ?>
                                    <option value="<?= (int)$adm['id'] ?>" <?= (int)$filterAdminId === (int)$adm['id'] ? 'selected' : '' ?>>
                                        <?= e($adm['name']) ?> (<?= ucfirst(e($adm['role'])) ?><?= ($adm['status'] ?? '') === 'retired' ? ', Retired' : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (!$inline_filters && $show_action && !empty($action_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Action</label>
                            <select name="action" class="form-control" style="width: 100%;">
                                <option value="">All Actions</option>
                                <?php foreach ($action_list as $act): ?>
                                    <option value="<?= e($act) ?>" <?= (string)$filterAction === (string)$act ? 'selected' : '' ?>>
                                        <?= e(ucwords(str_replace('_', ' ', $act))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_author && !empty($authors_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Author</label>
                            <select name="author" class="form-control" style="width: 100%;">
                                <option value="">All Authors</option>
                                <?php foreach ($authors_list as $auth): ?>
                                    <option value="<?= (int)$auth['id'] ?>" <?= (string)$filterAuthor === (string)$auth['id'] ? 'selected' : '' ?>>
                                        <?= e($auth['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_dept && !empty($departments)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Department</label>
                            <select name="department" class="form-control" style="width: 100%;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= e($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_sem): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Semester</label>
                            <select name="semester" class="form-control" style="width: 100%;">
                                <option value="">All Semesters</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filterSem === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_status && !empty($status_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Status</label>
                            <select name="status" class="form-control" style="width: 100%;">
                                <option value="">All Statuses</option>
                                <?php foreach ($status_list as $value => $label): ?>
                                    <?php
                                    if (is_numeric($value)) {
                                        $value = $label;
                                        $label = ucfirst($label);
                                    }
                                    ?>
                                    <option value="<?= e($value) ?>" <?= (string)$filterStatus === (string)$value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--color-border); padding-top: 16px;">
                    <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="font-size: 0.9rem;">Clear All</a>
                    <button type="submit" class="btn btn-primary" style="font-size: 0.9rem;">Apply Filters</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </form>
</div>

<script>
(function () {
    'use strict';

    const form    = document.getElementById('globalFilterForm');
    const input   = document.getElementById('liveSearchInput');
    const wrapper = document.getElementById('searchWrapper');
    if (!form || !input) return;

    const DELAY     = parseInt(form.dataset.liveDelay, 10) || 400;
    const MIN_CHARS = parseInt(form.dataset.liveMin, 10) || 0;
    const SELECTOR  = form.dataset.liveTarget;

    let timer        = null;   // debounce timer
    let controller   = null;   // AbortController for in-flight request
    let lastQuery    = input.value.trim();
    let composing    = false;  // true while typing with an IME (Hindi, Bengali, etc.)
    let forceNative  = false;  // true when we deliberately want a full-page submit

    /* ---------- Restore focus after a full page reload ---------- */
    const FOCUS_KEY = 'filterBarRefocus';
    function refocusInput() {
        input.focus({ preventScroll: true });
        const end = input.value.length;
        try { input.setSelectionRange(end, end); } catch (_) { /* type=search edge cases */ }
    }
    const hasQuery = new URLSearchParams(location.search).get('q');
    if (sessionStorage.getItem(FOCUS_KEY) === '1' || hasQuery) {
        refocusInput();
    }
    sessionStorage.removeItem(FOCUS_KEY);

    // Any real (non-AJAX) navigation from this bar should refocus the input on the next load
    form.addEventListener('submit', function () {
        sessionStorage.setItem(FOCUS_KEY, '1');
    });
    form.querySelectorAll('a[href]').forEach(function (a) {
        a.addEventListener('click', function () { sessionStorage.setItem(FOCUS_KEY, '1'); });
    });
    // Back/forward cache restores: make sure focus comes back too
    window.addEventListener('pageshow', function (e) { if (e.persisted && input.value) refocusInput(); });

    /* ---------- Build the URL from the current form state ---------- */
    function buildUrl() {
        const url  = new URL(form.getAttribute('action') || location.href, location.href);
        const data = new FormData(form);
        url.search = '';
        for (const [k, v] of data.entries()) {
            if (typeof v === 'string' && v.trim() !== '') {
                url.searchParams.set(k, v.trim());
            }
        }
        url.searchParams.delete('page'); // new search → back to page 1
        return url;
    }

    function setLoading(on) {
        wrapper.classList.toggle('is-loading', on);
        const target = document.querySelector(SELECTOR);
        if (target) target.classList.toggle('live-loading', on);
    }

    /* ---------- Fetch results & swap only the results area ---------- */
    async function runSearch(customUrl, isPagination) {
        const target = document.querySelector(SELECTOR);

        // If the page has no results container, fall back to a normal submit
        if (!target) {
            form.requestSubmit();
            return;
        }

        // Cancel any older request still running (prevents stale results)
        if (controller) controller.abort();
        controller = new AbortController();
        const myController = controller;

        const url = customUrl || buildUrl();
        setLoading(true);

        try {
            const res = await fetch(url.toString(), {
                signal: myController.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const html = await res.text();
            const doc  = new DOMParser().parseFromString(html, 'text/html');
            const fresh = doc.querySelector(SELECTOR);
            if (!fresh) throw new Error('Results container not found in response');

            target.innerHTML = fresh.innerHTML;
            sessionStorage.removeItem(FOCUS_KEY); // no reload happened, so nothing to restore

            // Keep the address bar in sync (shareable / refresh-safe) without a reload
            // Pagination gets its own history entry so the Back button goes to the previous page
            if (isPagination) {
                history.pushState(null, '', url.toString());
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                history.replaceState(null, '', url.toString());
            }

            // Let other scripts re-bind tooltips, checkboxes, etc.
            document.dispatchEvent(new CustomEvent('live-search:updated', { detail: { target } }));
        } catch (err) {
            if (err.name === 'AbortError') return; // superseded by a newer request
            console.error('Live search failed, falling back to full submit:', err);
            forceNative = true;
            form.requestSubmit();
            return;
        } finally {
            if (controller === myController) setLoading(false);
        }
    }

    /* ---------- Debounced trigger ---------- */
    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(function () {
            const q = input.value.trim();
            if (q === lastQuery) return;                       // nothing changed
            if (q.length > 0 && q.length < MIN_CHARS) return;  // too short (but allow clearing)
            lastQuery = q;
            runSearch();
        }, DELAY);
    }

    input.addEventListener('input', function () {
        if (!composing) schedule();
    });
    input.addEventListener('compositionstart', function () { composing = true; });
    input.addEventListener('compositionend',   function () { composing = false; schedule(); });

    // Pressing Enter searches immediately (still works, just not required)
    form.addEventListener('submit', function (e) {
        clearTimeout(timer);
        if (!forceNative && document.querySelector(SELECTOR) && e.submitter === null) {
            e.preventDefault();
            lastQuery = input.value.trim();
            runSearch();
        }
    });

    // Auto-trigger search when changing year or other top-bar select filters
    form.querySelectorAll('select[name="year"], .modern-inline-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            clearTimeout(timer);
            if (!forceNative && document.querySelector(SELECTOR)) {
                runSearch();
            } else {
                form.submit();
            }
        });
    });

    // AJAX pagination: intercept page links inside the results container.
    // Delegated on document, so it keeps working after the results are swapped.
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const link = e.target.closest('a[href]');
        const target = document.querySelector(SELECTOR);
        if (!link || !target || !target.contains(link)) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        let url;
        try { url = new URL(link.href, location.href); } catch (_) { return; }

        // Only same-page links that carry a ?page= param count as pagination
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        if (!url.searchParams.has('page')) return;

        e.preventDefault();
        clearTimeout(timer);
        runSearch(url, true);
    });

    // Re-run the search on the browser back/forward button
    window.addEventListener('popstate', function () { location.reload(); });
})();

/* ---------- Modal helpers ---------- */
function toggleFilterModal() {
    const modal = document.getElementById('filterModal');
    if (modal) modal.classList.toggle('active');
}

function handleModalClick(event) {
    if (event.target.classList.contains('filter-modal-overlay')) {
        toggleFilterModal();
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('filterModal');
        if (modal && modal.classList.contains('active')) toggleFilterModal();
    }
});
</script>
