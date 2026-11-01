/**
 * Examify Admin Instant SPA Router (Vanilla PJAX + Speculative Pre-fetching)
 *
 * Provides instantaneous, zero-delay view transitions across admin panel pages
 * without full browser reloads, preserving established URLs, browser history,
 * CSRF protection, and 100% offline air-gapped Zero-CDN architecture.
 */
(function () {
    'use strict';

    // Guard: Browser must support modern fetch, history, and DOMParser
    if (!window.fetch || !window.history || !window.DOMParser || !window.Promise) {
        return;
    }

    const CACHE_TTL_MS = 3 * 60 * 1000; // 3 minutes TTL
    const PREFETCH_IDLE_DELAY = 400; // ms after page load to prefetch sidebar links
    const cache = new Map(); // url -> { html, title, bodyClass, timestamp }
    const inFlightRequests = new Map(); // url -> Promise<PageData>

    let currentActiveUrl = normalizeUrl(window.location.href);
    let activeLoaderTimer = null;
    let progressBar = null;

    /**
     * Map secondary/child views to parent navigation item (matches admin-sidebar.php)
     */
    const ROUTE_PARENTS = {
        'manage-exam.php': 'control-exams.php',
        'proctor-exam.php': 'control-exams.php',
        'view-questions.php': 'manage-questions.php',
        'edit-question.php': 'manage-questions.php',
        'view-results.php': 'results.php',
        'view-student-answers.php': 'results.php'
    };

    /**
     * Normalize URLs for reliable cache keys and comparisons
     */
    function normalizeUrl(rawUrl) {
        try {
            const parsed = new URL(rawUrl, window.location.href);
            return parsed.pathname + parsed.search;
        } catch {
            return rawUrl;
        }
    }

    /**
     * Determine whether an URL should be handled via the SPA router
     */
    function shouldIntercept(rawUrl) {
        if (!rawUrl) return false;
        try {
            const target = new URL(rawUrl, window.location.href);

            // Must be same origin
            if (target.origin !== window.location.origin) return false;

            // Must be within /admin/ directory
            if (!target.pathname.includes('/admin/')) return false;

            // Skip authentication logout or password resets
            if (target.pathname.endsWith('admin-logout.php')) return false;

            // Skip PDF downloads or file exports
            if (target.searchParams.has('download_pdf') || target.searchParams.has('export')) return false;

            // Skip documentation links
            if (target.pathname.includes('/docs/')) return false;

            // Must target PHP files or directory root
            if (!target.pathname.endsWith('.php') && !target.pathname.endsWith('/admin/')) return false;

            return true;
        } catch {
            return false;
        }
    }

    /**
     * Create or retrieve top progress bar
     */
    function getProgressBar() {
        if (!progressBar) {
            progressBar = document.getElementById('admin-spa-loader');
            if (!progressBar) {
                progressBar = document.createElement('div');
                progressBar.id = 'admin-spa-loader';
                document.body.appendChild(progressBar);
            }
        }
        return progressBar;
    }

    function showLoader() {
        clearTimeout(activeLoaderTimer);
        activeLoaderTimer = setTimeout(() => {
            const bar = getProgressBar();
            bar.className = 'loading';
        }, 60);
    }

    function hideLoader() {
        clearTimeout(activeLoaderTimer);
        const bar = getProgressBar();
        if (bar.classList.contains('loading')) {
            bar.className = 'done';
            setTimeout(() => {
                bar.className = '';
            }, 350);
        } else {
            bar.className = '';
        }
    }

    /**
     * Parse HTML text response and extract admin viewport & metadata
     */
    function parsePage(htmlText, url) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');

        const newViewport = doc.getElementById('adminAppViewport');
        if (!newViewport) {
            return null; // Not an SPA-compatible page or session expired
        }

        // Detect if server redirected to login page due to expired session
        if (doc.querySelector('form[action*="admin-login"]') ||
            doc.title.toLowerCase().includes('login') ||
            doc.querySelector('.login-container')) {
            return null;
        }

        const title = doc.querySelector('title')?.innerText || document.title;
        const bodyClass = doc.body?.className || '';

        return {
            viewportHtml: newViewport.innerHTML,
            title: title,
            bodyClass: bodyClass,
            url: url,
            timestamp: Date.now()
        };
    }

    /**
     * Fetch page data with deduplication and caching
     */
    function fetchPage(url) {
        const cleanUrl = normalizeUrl(url);

        // Check active in-memory cache
        const cached = cache.get(cleanUrl);
        if (cached && (Date.now() - cached.timestamp < CACHE_TTL_MS)) {
            return Promise.resolve(cached);
        }

        // Deduplicate in-flight requests
        if (inFlightRequests.has(cleanUrl)) {
            return inFlightRequests.get(cleanUrl);
        }

        const fetchPromise = window.fetch(cleanUrl, {
            headers: {
                'X-Requested-With': 'Examify-SPA'
            }
        })
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP Error: ${res.status}`);
                }
                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('text/html')) {
                    throw new Error('Not an HTML document');
                }
                return res.text();
            })
            .then(html => {
                const parsed = parsePage(html, cleanUrl);
                if (!parsed) {
                    throw new Error('SPA Viewport not found in response');
                }
                cache.set(cleanUrl, parsed);
                return parsed;
            })
            .finally(() => {
                inFlightRequests.delete(cleanUrl);
            });

        inFlightRequests.set(cleanUrl, fetchPromise);
        return fetchPromise;
    }

    /**
     * Speculatively prefetch an URL in the background
     */
    function prefetch(url) {
        const cleanUrl = normalizeUrl(url);
        if (!shouldIntercept(cleanUrl)) return;
        if (cache.has(cleanUrl) || inFlightRequests.has(cleanUrl)) return;

        fetchPage(cleanUrl).catch(() => {
            // Silently ignore prefetch failures; normal click will retry or fall back
        });
    }

    /**
     * Re-execute scripts present inside newly swapped viewport
     */
    function reexecuteScripts(container) {
        const scripts = Array.from(container.querySelectorAll('script'));
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => {
                newScript.setAttribute(attr.name, attr.value);
            });
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // Dispatch standard and custom ready events
        document.dispatchEvent(new Event('DOMContentLoaded'));
        window.dispatchEvent(new Event('load'));
        document.dispatchEvent(new CustomEvent('examify:page-loaded'));
    }

    /**
     * Update active CSS indicator across sidebar links and topbar shortcuts
     */
    function updateActiveIndicators(url) {
        const currentPath = normalizeUrl(url).split('?')[0].split('#')[0];
        const basename = currentPath.split('/').pop() || 'admin-dashboard.php';
        const activeNavKey = ROUTE_PARENTS[basename] || basename;

        // Sidebar navigation links
        document.querySelectorAll('.sidebar-links a').forEach(a => {
            const href = a.getAttribute('href');
            if (!href) return;
            const targetBase = href.split('?')[0].split('#')[0].split('/').pop();
            if (targetBase === activeNavKey) {
                a.classList.add('active');
            } else {
                a.classList.remove('active');
            }
        });

        // Topbar shortcut action icons
        document.querySelectorAll('.topbar-shortcut').forEach(a => {
            const href = a.getAttribute('href');
            if (!href) return;
            const targetBase = href.split('?')[0].split('#')[0].split('/').pop();
            if (targetBase === activeNavKey) {
                a.classList.add('active');
            } else {
                a.classList.remove('active');
            }
        });
    }

    /**
     * Clean up any active WebSockets or long-polling instances when navigating
     */
    function cleanupActiveInstances() {
        if (window.examSocket && typeof window.examSocket.close === 'function') {
            try { window.examSocket.close(); } catch { /* ignore */ }
            window.examSocket = null;
        }
    }

    /**
     * Perform instantaneous viewport swap
     */
    function renderPage(pageData, pushState) {
        const viewport = document.getElementById('adminAppViewport');
        if (!viewport) {
            window.location.href = pageData.url;
            return;
        }

        cleanupActiveInstances();

        // Update document title and body class
        document.title = pageData.title;
        if (pageData.bodyClass) {
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            document.body.className = pageData.bodyClass;
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            }
        }

        // Swap viewport content with subtle transition
        viewport.innerHTML = pageData.viewportHtml;
        viewport.classList.remove('spa-transition-fade');
        void viewport.offsetWidth; // Force CSS reflow
        viewport.classList.add('spa-transition-fade');

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'instant' });

        // Update navigation indicators
        updateActiveIndicators(pageData.url);
        currentActiveUrl = normalizeUrl(pageData.url);

        // Update browser history
        if (pushState) {
            window.history.pushState({ url: pageData.url }, pageData.title, pageData.url);
        }

        // Close mobile sidebar overlay if open
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (adminSidebar) adminSidebar.classList.remove('show');
        if (sidebarOverlay) sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';

        // Execute scripts in newly rendered DOM
        reexecuteScripts(viewport);
    }

    /**
     * Main navigation handler
     */
    function navigateTo(url, pushState = true) {
        const cleanUrl = normalizeUrl(url);

        // If clicking link for the page currently rendered in the DOM, simply scroll to top
        if (pushState && cleanUrl === currentActiveUrl) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const cached = cache.get(cleanUrl);
        if (cached && (Date.now() - cached.timestamp < CACHE_TTL_MS)) {
            // Instant 0ms transition!
            renderPage(cached, pushState);
            return;
        }

        // Not cached yet: show progress loader and fetch
        showLoader();
        fetchPage(cleanUrl)
            .then(pageData => {
                hideLoader();
                renderPage(pageData, pushState);
            })
            .catch(() => {
                hideLoader();
                // Graceful fallback to native browser navigation
                window.location.href = cleanUrl;
            });
    }

    // ---------- Event Delegation & Listeners ----------

    // Global click listener for SPA navigation
    document.addEventListener('click', function (e) {
        // Skip right-click, middle-click, or key modifiers
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        const link = e.target.closest('a');
        if (!link) return;

        // Skip if link has explicit target, hash only, or opt-out attribute
        if (link.getAttribute('target') === '_blank' ||
            link.hasAttribute('data-no-pjax') ||
            link.getAttribute('href')?.startsWith('#')) {
            return;
        }

        const href = link.getAttribute('href');
        if (!href) return;

        if (shouldIntercept(href)) {
            e.preventDefault();
            navigateTo(href, true);
        }
    });

    // Hover / touchstart speculative prefetching
    document.addEventListener('mouseover', function (e) {
        const link = e.target.closest('a');
        if (link && !link.hasAttribute('data-no-pjax')) {
            const href = link.getAttribute('href');
            if (href) prefetch(href);
        }
    }, { passive: true });

    document.addEventListener('touchstart', function (e) {
        const link = e.target.closest('a');
        if (link && !link.hasAttribute('data-no-pjax')) {
            const href = link.getAttribute('href');
            if (href) prefetch(href);
        }
    }, { passive: true });

    // Handle browser back and forward history buttons
    window.addEventListener('popstate', function (e) {
        navigateTo(window.location.href, false);
    });

    // Invalidate cache upon any form POST submission so updated records render fresh
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form && form.method && form.method.toUpperCase() === 'POST') {
            cache.clear();
        }
    });

    // Initial idle prefetch of visible sidebar links
    setTimeout(() => {
        document.querySelectorAll('.sidebar-links a').forEach(a => {
            const href = a.getAttribute('href');
            if (href && !href.startsWith('#')) {
                prefetch(href);
            }
        });
    }, PREFETCH_IDLE_DELAY);

    // Initial page cache population for instant back-navigation
    const currentViewport = document.getElementById('adminAppViewport');
    if (currentViewport) {
        cache.set(normalizeUrl(window.location.href), {
            viewportHtml: currentViewport.innerHTML,
            title: document.title,
            bodyClass: document.body.className,
            url: normalizeUrl(window.location.href),
            timestamp: Date.now()
        });
    }

    // Expose utility on window for programmatic navigation
    window.ExamifyRouter = {
        navigateTo: navigateTo,
        prefetch: prefetch,
        clearCache: () => cache.clear()
    };
})();
