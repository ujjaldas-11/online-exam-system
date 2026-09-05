<?php
/**
 * Examify — Instructor & Administrator Documentation
 * Restricted strictly to active Teacher and Superadmin accounts.
 */

require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/session.php';

if (session_status() === PHP_SESSION_NONE) {
    init_secure_session();
}

// Enforce admin login
if (!is_admin_logged_in()) {
    set_flash('error', 'Administrator login required. Please log in as a Teacher or Superadmin.');
    header('Location: ../../admin/admin-login.php');
    exit;
}

// Verify active account status, role, and singleton session
if (isset($pdo) && !empty($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status, role, name, active_session_id FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $currAdmin = $stmt->fetch();

        if (!$currAdmin || ($currAdmin['status'] ?? 'active') === 'retired') {
            destroy_user_session('../../admin/admin-login.php?error=retired');
            exit;
        }

        // Singleton session check: terminate if logged in on another device
        $currentSessionId = session_id();
        if (!empty($currAdmin['active_session_id']) && $currAdmin['active_session_id'] !== $currentSessionId) {
            destroy_user_session('../../admin/admin-login.php?error=concurrent_session');
            exit;
        }

        $_SESSION['admin_name'] = $currAdmin['name'];
        $_SESSION['admin_role'] = $currAdmin['role'];
        $_SESSION['role'] = $currAdmin['role'];
    } catch (PDOException) {}
}

$adminRole = get_admin_role();
if ($adminRole !== 'teacher' && $adminRole !== 'superadmin') {
    http_response_code(403);
    die('Access Denied: Teacher or Superadmin privileges required.');
}

$adminName = $_SESSION['admin_name'] ?? 'Faculty Member';
$isSuper = is_superadmin();
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Examify — Administrator Documentation</title>
        <!-- Self-contained typography with system fallbacks for zero-CDN offline access -->
        <style>
            :root {
                --ink: #1b2a41;
                --ink-soft: #3d4d63;
                --paper: #eeebe1;
                --paper-panel: #fbfaf6;
                --rule: #d9d3c1;
                --rule-strong: #c3bca6;
                --blue: #2c6e9e;
                --blue-deep: #1f5378;
                --gold: #b8862b;
                --gold-deep: #8f6a1f;
                --gray-box: #8b8878;
                --red: #a53d28;
                --green: #3f7a5c;
                --shadow:
                    0 1px 2px rgba(27, 42, 65, 0.06),
                    0 6px 20px rgba(27, 42, 65, 0.06);
                --radius: 3px;
            }
            * {
                box-sizing: border-box;
            }
            html {
                scroll-behavior: smooth;
            }
            body {
                margin: 0;
                background: var(--paper);
                color: var(--ink);
                font-family: "IBM Plex Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 16px;
                line-height: 1.6;
            }
            ::selection {
                background: var(--gold);
                color: #fff;
            }

            h1,
            h2,
            h3,
            h4 {
                font-family: "Fraunces", Georgia, Cambria, "Times New Roman", Times, serif;
                color: var(--ink);
                letter-spacing: -0.01em;
                margin: 0 0 0.5em 0;
            }
            code,
            pre,
            .mono {
                font-family: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            }
            a {
                color: var(--blue-deep);
            }

            /* ===== Top bar ===== */
            .topbar {
                position: sticky;
                top: 0;
                z-index: 50;
                background: var(--ink);
                color: #f1ede0;
                border-bottom: 3px solid var(--gold);
            }
            .topbar-inner {
                max-width: 1280px;
                margin: 0 auto;
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 12px 28px;
            }
            .brand {
                display: flex;
                align-items: center;
                gap: 10px;
                font-family: "Fraunces", serif;
                font-weight: 700;
                font-size: 1.25rem;
                color: #fbfaf6;
                white-space: nowrap;
                text-decoration: none;
            }
            .brand .dot-grid {
                display: flex;
                gap: 3px;
            }
            .brand .dot-grid span {
                width: 7px;
                height: 7px;
                border-radius: 1px;
                display: block;
            }
            .brand .dot-grid span:nth-child(1) { background: var(--blue); }
            .brand .dot-grid span:nth-child(2) { background: var(--gold); }
            .brand .dot-grid span:nth-child(3) { background: var(--gray-box); }

            .badge-doc-type {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                background: rgba(184, 134, 43, 0.25);
                color: #ffd700;
                border: 1px solid rgba(255, 215, 0, 0.35);
                padding: 4px 10px;
                border-radius: 12px;
            }

            .user-chip {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.78rem;
                color: #e2ded0;
                background: rgba(255, 255, 255, 0.08);
                padding: 4px 12px;
                border-radius: 14px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .user-chip strong {
                color: #ffd700;
            }

            .topbar-links {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-left: auto;
            }
            .topbar-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 14px;
                border-radius: 18px;
                font-size: 0.84rem;
                font-weight: 600;
                text-decoration: none;
                transition: background 0.2s, color 0.2s;
            }
            .topbar-btn-secondary {
                background: rgba(255, 255, 255, 0.08);
                color: #e2ded0;
                border: 1px solid rgba(255, 255, 255, 0.15);
            }
            .topbar-btn-secondary:hover {
                background: rgba(255, 255, 255, 0.18);
                color: #fff;
            }
            .topbar-btn-gold {
                background: var(--gold);
                color: #1b2a41;
            }
            .topbar-btn-gold:hover {
                background: #cf9b3a;
            }

            .search-wrap {
                position: relative;
            }
            .search-wrap input {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.18);
                color: #fbfaf6;
                padding: 7px 12px 7px 32px;
                border-radius: 20px;
                font-family: "IBM Plex Sans", sans-serif;
                font-size: 0.85rem;
                width: 180px;
                transition: width 0.2s, background 0.2s;
            }
            .search-wrap input::placeholder {
                color: #9c9782;
            }
            .search-wrap input:focus {
                outline: none;
                width: 230px;
                background: rgba(255, 255, 255, 0.14);
            }
            .search-wrap svg {
                position: absolute;
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
                opacity: 0.6;
            }

            /* ===== Layout ===== */
            .shell {
                max-width: 1280px;
                margin: 0 auto;
                display: grid;
                grid-template-columns: 270px 1fr;
                gap: 0;
            }
            .sidebar {
                position: sticky;
                top: 57px;
                align-self: start;
                height: calc(100vh - 57px);
                overflow-y: auto;
                padding: 28px 16px 40px 28px;
                border-right: 1px solid var(--rule);
                scrollbar-width: thin;
            }
            .sidebar::-webkit-scrollbar {
                width: 6px;
            }
            .sidebar::-webkit-scrollbar-thumb {
                background: var(--rule-strong);
                border-radius: 3px;
            }

            .toc-group {
                margin-bottom: 22px;
            }
            .toc-role-label {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.68rem;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--ink-soft);
                margin: 0 0 8px 4px;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .toc-role-label .chip {
                width: 8px;
                height: 8px;
                border-radius: 2px;
                display: inline-block;
            }
            .toc-group ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .toc-group > ul > li {
                margin-bottom: 2px;
            }
            .toc-link {
                display: block;
                padding: 6px 10px;
                border-radius: var(--radius);
                color: var(--ink-soft);
                text-decoration: none;
                font-size: 0.88rem;
                border-left: 2px solid transparent;
            }
            .toc-link.top {
                font-weight: 600;
                color: var(--ink);
            }
            .toc-group ul ul {
                margin-left: 10px;
                border-left: 1px dashed var(--rule-strong);
            }
            .toc-link:hover {
                background: rgba(27, 42, 65, 0.05);
                color: var(--ink);
            }
            .toc-link.active {
                background: #fff;
                color: var(--ink);
                border-left: 2px solid var(--gold);
                box-shadow: var(--shadow);
                font-weight: 600;
            }

            main {
                padding: 0 40px 100px 40px;
                min-width: 0;
            }

            /* ===== Hero ===== */
            .hero {
                padding: 56px 0 40px 0;
                border-bottom: 1px solid var(--rule);
                display: grid;
                grid-template-columns: 1.3fr 1fr;
                gap: 40px;
                align-items: center;
            }
            .hero-eyebrow {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.75rem;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: var(--gold-deep);
                margin-bottom: 10px;
                font-weight: 600;
            }
            .hero h1 {
                font-size: 2.9rem;
                font-weight: 700;
                line-height: 1.05;
                margin-bottom: 14px;
            }
            .hero p.lede {
                font-size: 1.08rem;
                color: var(--ink-soft);
                max-width: 46ch;
            }
            .hero-stats {
                display: flex;
                gap: 28px;
                margin-top: 26px;
            }
            .hero-stat .num {
                font-family: "Fraunces", serif;
                font-size: 1.7rem;
                font-weight: 700;
            }
            .hero-stat .label {
                font-size: 0.78rem;
                color: var(--ink-soft);
                text-transform: uppercase;
                letter-spacing: 0.06em;
            }

            /* Admin summary panel */
            .admin-kpi-panel {
                background: var(--paper-panel);
                border: 1px solid var(--rule);
                border-radius: 6px;
                box-shadow: var(--shadow);
                padding: 22px;
            }
            .admin-kpi-panel h3 {
                font-size: 1.15rem;
                margin: 0 0 12px 0;
                color: var(--ink);
            }
            .admin-kpi-panel p {
                margin: 0 0 14px 0;
                font-size: 0.9rem;
                color: var(--ink-soft);
            }
            .kpi-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .kpi-box {
                background: #fff;
                border: 1px solid var(--rule);
                border-radius: 4px;
                padding: 10px 14px;
            }
            .kpi-box .val {
                font-family: "Fraunces", serif;
                font-size: 1.3rem;
                font-weight: 700;
                color: var(--gold-deep);
            }
            .kpi-box .title {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.7rem;
                text-transform: uppercase;
                color: var(--ink-soft);
            }

            /* ===== Section blocks ===== */
            section.doc-section {
                padding-top: 52px;
                scroll-margin-top: 72px;
            }
            .section-kicker {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.75rem;
                color: var(--ink-soft);
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 6px;
            }
            section.doc-section h2 {
                font-size: 1.9rem;
                padding-bottom: 14px;
                border-bottom: 2px solid var(--rule);
                margin-bottom: 22px;
            }

            .subsection {
                margin: 0 0 18px 0;
                background: var(--paper-panel);
                border: 1px solid var(--rule);
                border-radius: 6px;
                box-shadow: var(--shadow);
                overflow: hidden;
                scroll-margin-top: 72px;
            }
            .subsection.filtered-out {
                display: none;
            }
            .subsection > summary {
                list-style: none;
                cursor: pointer;
                padding: 16px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                font-family: "Fraunces", serif;
                font-weight: 600;
                font-size: 1.08rem;
            }
            .subsection > summary::-webkit-details-marker {
                display: none;
            }
            .subsection > summary .arrow {
                font-family: "IBM Plex Mono", monospace;
                color: var(--ink-soft);
                font-size: 0.9rem;
                transition: transform 0.2s;
                flex-shrink: 0;
            }
            .subsection[open] > summary .arrow {
                transform: rotate(90deg);
            }
            .subsection > summary:hover {
                background: rgba(27, 42, 65, 0.03);
            }
            .subsection .body {
                padding: 0 22px 22px 22px;
                color: var(--ink-soft);
            }
            .subsection .body p {
                margin: 0 0 12px 0;
            }
            .subsection .body ol,
            .subsection .body ul {
                padding-left: 22px;
                margin: 0 0 14px 0;
            }
            .subsection .body li {
                margin-bottom: 6px;
            }
            .subsection .body strong {
                color: var(--ink);
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0 16px 0;
                font-size: 0.92rem;
            }
            th,
            td {
                text-align: left;
                padding: 9px 12px;
                border-bottom: 1px solid var(--rule);
            }
            th {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--ink-soft);
                background: rgba(27, 42, 65, 0.03);
            }

            .admonition {
                border-left: 4px solid var(--blue);
                background: #eef4f8;
                padding: 12px 16px;
                border-radius: 0 4px 4px 0;
                margin: 14px 0;
                font-size: 0.92rem;
            }
            .admonition.warning {
                border-left-color: var(--red);
                background: #f8ece7;
            }
            .admonition .adm-label {
                font-family: "IBM Plex Mono", monospace;
                font-weight: 700;
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                display: block;
                margin-bottom: 4px;
            }
            .admonition.warning .adm-label { color: var(--red); }
            .admonition:not(.warning) .adm-label { color: var(--blue-deep); }

            .code-block {
                position: relative;
                background: var(--ink);
                color: #e9e5d6;
                border-radius: 6px;
                padding: 16px 18px;
                font-size: 0.84rem;
                overflow-x: auto;
                margin: 12px 0 16px 0;
            }
            .code-block .copy-btn {
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(255, 255, 255, 0.1);
                color: #e9e5d6;
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 4px;
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.7rem;
                padding: 4px 8px;
                cursor: pointer;
            }
            .code-block .copy-btn:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            .code-block .str { color: #8fc4e0; }
            .code-block .key { color: #e0b96a; }

            .badge {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.72rem;
                font-weight: 600;
                padding: 4px 9px;
                border-radius: 12px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .badge.live { background: #e5f0ea; color: var(--green); }
            .badge.scheduled { background: #eef1f6; color: var(--blue-deep); }
            .badge.ended { background: #efece5; color: var(--ink-soft); }

            .role-pill {
                display: inline-block;
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                padding: 2px 8px;
                border-radius: 10px;
                margin-left: 8px;
                vertical-align: middle;
                font-weight: 600;
            }
            .role-pill.admin {
                background: #f4ecdc;
                color: var(--gold-deep);
            }

            footer {
                border-top: 1px solid var(--rule);
                padding: 28px 40px;
                text-align: center;
                color: var(--ink-soft);
                font-size: 0.85rem;
            }

            .no-results {
                display: none;
                padding: 40px;
                text-align: center;
                color: var(--ink-soft);
                font-family: "IBM Plex Mono", monospace;
            }

            @media (max-width: 900px) {
                .shell { grid-template-columns: 1fr; }
                .sidebar {
                    position: static;
                    height: auto;
                    border-right: none;
                    border-bottom: 1px solid var(--rule);
                }
                .hero { grid-template-columns: 1fr; }
                main { padding: 0 18px 80px 18px; }
                .topbar-inner { flex-wrap: wrap; gap: 10px; padding: 12px 16px; }
                .search-wrap { order: 3; width: 100%; margin-left: 0; }
                .search-wrap input { width: 100%; }
                .topbar-links { width: 100%; justify-content: space-between; }
            }

            :focus-visible {
                outline: 2px solid var(--gold);
                outline-offset: 2px;
            }
            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto; }
                * { transition: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <div class="topbar-inner">
                <a href="../../admin/admin-dashboard.php" class="brand">
                    <span class="dot-grid"><span></span><span></span><span></span></span>
                    Examify Docs
                </a>
                <span class="badge-doc-type">Instructor & Admin Guide</span>

                <div class="user-chip">
                    <span>Authenticated:</span>
                    <strong><?= htmlspecialchars($adminName) ?> (<?= ucfirst($adminRole) ?>)</strong>
                </div>

                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input id="searchInput" type="text" placeholder="Search admin topics…" />
                </div>

                <div class="topbar-links">
                    <a href="../../admin/admin-dashboard.php" class="topbar-btn topbar-btn-gold">Dashboard</a>
                    <a href="user-doc.php" class="topbar-btn topbar-btn-secondary">User Docs</a>
                    <a href="../../admin/admin-logout.php" class="topbar-btn topbar-btn-secondary">Logout</a>
                </div>
            </div>
        </div>

        <div class="shell">
            <nav class="sidebar" id="sidebar">
                <div class="toc-group">
                    <div class="toc-role-label">
                        <span class="chip" style="background: var(--gray-box)"></span>Overview
                    </div>
                    <ul>
                        <li><a class="toc-link top" href="#sec-1">1. Institutional RBAC</a></li>
                    </ul>
                </div>

                <div class="toc-group">
                    <div class="toc-role-label">
                        <span class="chip" style="background: var(--gold)"></span>Instructor & Admin
                    </div>
                    <ul>
                        <li>
                            <a class="toc-link top" href="#sec-3">3. Administrator Guide</a>
                            <ul>
                                <li><a class="toc-link" href="#sec-3-1">3.1 Authentication & Security</a></li>
                                <li><a class="toc-link" href="#sec-3-2">3.2 Dashboard & Analytics</a></li>
                                <li><a class="toc-link" href="#sec-3-3">3.3 Student Management</a></li>
                                <li><a class="toc-link" href="#sec-3-4">3.4 Bulk Student Promotion</a></li>
                                <li><a class="toc-link" href="#sec-3-5">3.5 Curriculum Subjects</a></li>
                                <li><a class="toc-link" href="#sec-3-6">3.6 Question Banks</a></li>
                                <li><a class="toc-link" href="#sec-3-7">3.7 Configuring Exams</a></li>
                                <li><a class="toc-link" href="#sec-3-8">3.8 Controlling Exams & Time</a></li>
                                <li><a class="toc-link" href="#sec-3-9">3.9 Live Proctoring Panel</a></li>
                                <li><a class="toc-link" href="#sec-3-10">3.10 Requests & Password Resets</a></li>
                                <li><a class="toc-link" href="#sec-3-11">3.11 Batch CSV Enrollment</a></li>
                                <li><a class="toc-link" href="#sec-3-12">3.12 Results & PDF Downloads</a></li>
                                <li><a class="toc-link" href="#sec-3-13">3.13 Teacher Provisioning & Retention</a></li>
                                <li><a class="toc-link" href="#sec-3-14">3.14 Institutional Audit Trail</a></li>
                                <li><a class="toc-link" href="#sec-3-15">3.15 Master Setup Wizard</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <main>
                <div class="hero">
                    <div>
                        <div class="hero-eyebrow">Faculty & Administration Handbook</div>
                        <h1>Control Center</h1>
                        <p class="lede">
                            Complete administrative guide for Examify instructors and superadmins.
                            Learn how to manage curriculum questions, launch live tests, monitor proctoring, manage student rosters, and generate official institutional records.
                        </p>
                        <div class="hero-stats">
                            <div class="hero-stat">
                                <div class="num"><?= $isSuper ? 'Superadmin' : 'Teacher' ?></div>
                                <div class="label">Your Authority</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">15</div>
                                <div class="label">Admin workflows</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">100%</div>
                                <div class="label">Retained records</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-kpi-panel">
                        <h3>Campus Exam Operations</h3>
                        <p>Real-time laboratory controls, anti-cheat surveillance, and permanent record retention.</p>
                        <div class="kpi-grid">
                            <div class="kpi-box">
                                <div class="val">Zero</div>
                                <div class="title">External CDN Dependencies</div>
                            </div>
                            <div class="kpi-box">
                                <div class="val">5 Sec</div>
                                <div class="title">Live Proctor Refresh</div>
                            </div>
                            <div class="kpi-box">
                                <div class="val">Pure PHP</div>
                                <div class="title">Native PDF Engine</div>
                            </div>
                            <div class="kpi-box">
                                <div class="val">Atomic</div>
                                <div class="title">High-Concurrency Saves</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1 -->
                <section class="doc-section" id="sec-1">
                    <div class="section-kicker">Governance & Authority</div>
                    <h2>1. Institutional Role-Based Access Control</h2>
                    <details class="subsection" open>
                        <summary>
                            Administrative Permissions Matrix<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Examify provides strict Role-Based Access Control (RBAC) across two faculty tiers:
                            </p>
                            <table>
                                <tr>
                                    <th>Feature / Module</th>
                                    <th>Teacher / Instructor</th>
                                    <th>Master Superadmin</th>
                                </tr>
                                <tr>
                                    <td>Manage Subjects & Question Banks</td>
                                    <td>✅ Full access</td>
                                    <td>✅ Full access</td>
                                </tr>
                                <tr>
                                    <td>Create, Launch & Control Exams</td>
                                    <td>✅ Full access</td>
                                    <td>✅ Full access</td>
                                </tr>
                                <tr>
                                    <td>Live Classroom Proctoring & Crash Unlocks</td>
                                    <td>✅ Full access</td>
                                    <td>✅ Full access</td>
                                </tr>
                                <tr>
                                    <td>Student Management & Bulk Promotion</td>
                                    <td>✅ Full access</td>
                                    <td>✅ Full access</td>
                                </tr>
                                <tr>
                                    <td>Provision, Retire & Reactivate Teachers</td>
                                    <td>❌ Restricted</td>
                                    <td>✅ Superadmin exclusive</td>
                                </tr>
                                <tr>
                                    <td>Audit Trail Inspection</td>
                                    <td>Own activity only</td>
                                    <td>Campus-wide faculty audit</td>
                                </tr>
                            </table>
                        </div>
                    </details>
                </section>

                <!-- SECTION 3 -->
                <section class="doc-section" id="sec-3">
                    <div class="section-kicker">Faculty Operational Manual</div>
                    <h2>
                        3. Instructor and Administrator Portal Guide
                        <span class="role-pill admin">Admin</span>
                    </h2>

                    <details class="subsection" open id="sec-3-1">
                        <summary>
                            3.1 Administrator Login & Security Policies<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Navigate to the administrator login page (<code>admin/admin-login.php</code>).</li>
                                <li>Type your institutional email address.</li>
                                <li>Type your password (use the password visibility toggle to confirm accuracy).</li>
                                <li>Click the <strong>Login as Admin</strong> button.</li>
                            </ol>
                            <div class="admonition warning">
                                <span class="adm-label">Singleton Administrative Session</span>
                                If an administrator logs in from another terminal, the system terminates older sessions immediately to prevent session hijacking.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-2">
                        <summary>
                            3.2 Admin Dashboard Overview<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>The dashboard provides immediate executive metrics:</p>
                            <ul>
                                <li><strong>Curriculum Subjects:</strong> Number of configured academic courses.</li>
                                <li><strong>Configured Examinations:</strong> Total tests scheduled or drafted.</li>
                                <li><strong>Live Examinations:</strong> Currently active computer lab exams.</li>
                                <li><strong>Question Bank Inventory:</strong> Total multiple-choice items stored.</li>
                                <li><strong>Enrolled Students:</strong> Active student directory count.</li>
                                <li><strong>Completed Attempts:</strong> Total graded test submissions.</li>
                            </ul>
                            <p>
                                The active sidebar tab highlights in <strong>Gold (<code>#ffd700</code>)</strong> for immediate orientation.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-3">
                        <summary>
                            3.3 Student Management Panel (<code>admin/manage-students.php</code>)<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Open the <strong>Students</strong> panel from the sidebar to govern the student lifecycle:
                            </p>
                            <h4>Directory Search & Filters</h4>
                            <p>
                                Filter students instantly by search text (name, email, roll number), Department (BCA, BBA), Semester (1–8), and Account Status (Active, Pending, Blocked, Rejected).
                            </p>
                            <h4>Enrolling a New Student</h4>
                            <ol>
                                <li>Click the <strong>Add Student</strong> button in the top action bar.</li>
                                <li>Type the student full name, email, roll number, department, semester, and password.</li>
                                <li>Click <strong>Create Student</strong>. The student is created directly with <code>active</code> status.</li>
                            </ol>
                            <h4>In-Place Profile Editing & Credential Resets</h4>
                            <ul>
                                <li>Click <strong>Edit</strong> on any row to modify student details.</li>
                                <li>Click <strong>Reset Password</strong> to assign a temporary password on demand. Setting a new password terminates any active student session immediately.</li>
                                <li>Click <strong>Block / Unblock</strong> to suspend or restore student examination privileges with one click.</li>
                                <li>Click <strong>Export CSV</strong> to download the current filtered roster to spreadsheet format with formula sanitization.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-4">
                        <summary>
                            3.4 Bulk Student Promotion<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Advance students to the next academic semester in batch mode without manual record edits:
                            </p>
                            <h4>1. Cohort-Based Bulk Promotion</h4>
                            <ol>
                                <li>Locate the <strong>Bulk Promote by Cohort</strong> panel.</li>
                                <li>Select the academic <strong>Department</strong> and <strong>Current Semester</strong>.</li>
                                <li>The target semester displays automatically (+1 semester).</li>
                                <li>Click <strong>Promote Cohort (+1 Sem)</strong> and confirm the warning dialog.</li>
                            </ol>
                            <h4>2. Selection-Based Bulk Promotion</h4>
                            <ol>
                                <li>In the student roster table, select the checkboxes next to target students.</li>
                                <li>A floating batch action bar appears at the bottom of your screen showing selected count.</li>
                                <li>Click <strong>Promote Selected (+1 Sem)</strong>.</li>
                            </ol>
                            <div class="admonition">
                                <span class="adm-label">Semester 8 Cap</span>
                                The bulk promotion engine automatically caps students at Semester 8, preventing invalid semester increments beyond undergraduate curriculum limits.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-5">
                        <summary>
                            3.5 Managing Curriculum Subjects<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click <strong>Subjects</strong> in the navigation sidebar.</li>
                                <li>Type the subject name (e.g., <code>Database Management Systems</code>).</li>
                                <li>Select the department and academic semester.</li>
                                <li>Click <strong>Create Subject</strong>.</li>
                            </ol>
                            <p>
                                Click <strong>View Questions</strong> next to any subject to inspect or expand its question bank.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-6">
                        <summary>
                            3.6 Managing Question Banks & Bulk CSV Upload<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Upload questions in bulk using standardized CSV format:</p>
                            <ol>
                                <li>Click <strong>Questions</strong> in the navigation sidebar (<code>admin/manage-questions.php</code>).</li>
                                <li>Select the destination curriculum subject from the dropdown menu.</li>
                                <li>(Optional) Click <strong>Download Template</strong> to inspect the exact column structure.</li>
                                <li>Upload your <code>.csv</code> or <code>.txt</code> file, or paste CSV records directly into the text area.</li>
                                <li>Click <strong>Upload Questions</strong>.</li>
                            </ol>
                            <p>The CSV requires 7 columns: <code>Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option</code></p>
                            <div class="code-block">
                                <button class="copy-btn" data-copy>Copy</button>
                                <pre style="margin: 0; white-space: pre-wrap">Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option
"What is an operating system?",1,"System software","Application software","Hardware component","Malicious program",A
"Which memory management technique uses variable partition sizes?",2,"Paging","Segmentation","Thrashing","Compaction",B
"What is a critical section in process synchronization?",3,"Code segment accessing shared variables","OS kernel code","Bootloader sector","CPU cache line",A
"Which scheduling algorithm is non-preemptive?",4,"FCFS","Round Robin","SRTF","Multilevel Queue",A</pre>
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-7">
                        <summary>
                            3.7 Configuring an Examination<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click <strong>Create Exam</strong> in the sidebar.</li>
                                <li>Type the examination title (e.g., <code>Operating Systems Quiz 1</code>).</li>
                                <li>Select the curriculum subject.</li>
                                <li>Set test duration in minutes.</li>
                                <li>Specify total examination marks and question count per student.</li>
                                <li>(Optional) Configure <strong>Negative Marks Per Question</strong> (e.g. <code>0.25</code> or <code>0.50</code> deduction for wrong answers; score floored at 0.00).</li>
                                <li>(Optional) Set a 4-digit <strong>Classroom PIN</strong> for surprise quizzes.</li>
                                <li>Click <strong>Create Examination</strong>.</li>
                            </ol>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-8">
                        <summary>
                            3.8 Controlling Examinations & Emergency Time Extensions<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Navigate to <strong>Exams</strong> (<code>admin/control-exams.php</code>) to govern examination states:
                            </p>
                            <table>
                                <tr><th>State</th><th>Controls</th></tr>
                                <tr>
                                    <td><span class="badge scheduled">Inactive</span></td>
                                    <td><strong>Start:</strong> Publish test live · <strong>Offline Paper:</strong> Download printable PDF with answer key · <strong>Delete:</strong> Remove test</td>
                                </tr>
                                <tr>
                                    <td><span class="badge live">Live</span></td>
                                    <td>
                                        <strong>Live Proctor:</strong> Open surveillance & broadcasts · 
                                        <strong>+5 min / +10 min:</strong> Grant emergency time · 
                                        <strong>End Exam:</strong> Terminate test
                                    </td>
                                </tr>
                                <tr>
                                    <td><span class="badge ended">Ended</span></td>
                                    <td><strong>Results:</strong> Inspect graded submissions and export PDF</td>
                                </tr>
                            </table>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-9">
                        <summary>
                            3.9 Live Classroom Proctoring Panel & Broadcast Announcements<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Open <strong>Live Proctor</strong> (<code>admin/proctor-exam.php?exam_id=...</code>) during tests.
                                The dashboard connects to the real-time WebSocket daemon with fallback HTTP polling.
                            </p>
                            <h4>Real-Time Candidate Metrics</h4>
                            <ul>
                                <li><strong>Total Class Roster:</strong> Enrolled students eligible for this assessment.</li>
                                <li><strong>Currently Answering:</strong> Candidates with active test attempts.</li>
                                <li><strong>Submitted / Done:</strong> Candidates who completed the exam.</li>
                                <li><strong>Total Cheating Flags:</strong> Fullscreen exits, tab switches, and window blur events detected.</li>
                            </ul>
                            <h4>Live Proctor Broadcast Announcements</h4>
                            <p>
                                Instructors can click <strong>Announce to Candidates</strong> to type a message. The message is pushed instantly across the real-time WebSocket channel and rendered in a prominent announcement banner on all active candidate screens.
                            </p>
                            <h4>Anti-Cheat Telemetry & Automated Disqualification</h4>
                            <p>
                                The examination engine actively monitors candidate workstations for unauthorized behaviors (exiting fullscreen, switching browser tabs, or window blur). Candidates receive warnings on their screen, and after <strong>3 violations</strong>, the server automatically marks the attempt as <code>disqualified</code>, locks further submissions, and alerts the proctoring dashboard in real time. Option letters are deterministically permuted per attempt to deter shoulder surfing.
                            </p>
                            <h4>Emergency Hardware Actions</h4>
                            <ul>
                                <li><strong>Unlock / Resume Attempt:</strong> If a student PC crashes, click Unlock to restore their test state to <code>in_progress</code> without losing saved answers.</li>
                                <li><strong>Force Submit:</strong> If a candidate leaves the lab or is disqualified, click Force Submit to evaluate their attempt immediately.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-10">
                        <summary>
                            3.10 Student Credential Requests & Offline Password Resets<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Open <strong>Requests</strong> (<code>admin/manage-requests.php</code>).</li>
                                <li>Review pending student profile change requests.</li>
                                <li>Click <strong>Approve</strong> to apply updates, or <strong>Reject</strong> to dismiss.</li>
                                <li>Use the <strong>Classroom Password Reset</strong> form to issue temporary passwords to students before lab quizzes.</li>
                            </ol>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-11">
                        <summary>
                            3.11 Batch CSV Student Enrollment Tool<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Enroll an entire class section in seconds via CSV import:</p>
                            <ol>
                                <li>Open <strong>Import</strong> (<code>admin/import-students.php</code>).</li>
                                <li>Format your CSV with columns: <code>Name, Email, Roll Number, Department, Semester, Password</code>.</li>
                                <li>Select the file or paste raw CSV text.</li>
                                <li>Click <strong>Import Classroom Roster</strong>.</li>
                            </ol>
                            <p>The importer skips duplicate emails and roll numbers automatically.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-12">
                        <summary>
                            3.12 Viewing Results & Downloading Institutional PDF Reports<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Navigate to <strong>Results</strong> (<code>admin/view-results.php</code>) to evaluate performance:</p>
                            <ul>
                                <li><strong>Podium:</strong> Gold, silver, and bronze highlights for top 3 rank holders.</li>
                                <li><strong>Roster Table:</strong> Student ranks, roll numbers, percentage scores, and submission timestamps.</li>
                                <li><strong>Download Results PDF:</strong> Generates an official institutional assessment report using pure-PHP FPDF. Includes KPI boxes, candidate rankings, and symmetrical institutional signature endorsement lines.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-13">
                        <summary>
                            3.13 Teacher Accounts, Provisioning & Permanent Record Retention<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Superadmins manage faculty credentials in <code>admin/manage-teachers.php</code>.
                            </p>
                            <div class="admonition">
                                <span class="adm-label">Institutional Guarantee</span>
                                When faculty leave or retire, their authored questions, exams, and student grades are <strong>100% retained</strong>. Foreign keys preserve author attribution permanently as <code>Prof. Name [Retired]</code>.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-14">
                        <summary>
                            3.14 Institutional Audit Trail (<code>admin/audit-logs.php</code>)<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                The audit trail logs every administrative operation: exam launches, duration extensions, question uploads, student enrollments, and password resets.
                                Records include administrator ID, role, action, target entity, client IP address, and timestamp.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-15">
                        <summary>
                            3.15 First-Time Master Setup Wizard (<code>admin/setup.php</code>)<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Fresh deployments automatically initialize the master superadmin setup wizard.
                                Once the initial superadmin is provisioned with <code>PASSWORD_BCRYPT</code>, the wizard locks permanently against execution to prevent privilege hijacking.
                            </p>
                        </div>
                    </details>
                </section>

                <div class="no-results" id="noResults">
                    No matching administrative topics found. Try a different search term.
                </div>
            </main>
        </div>

        <footer>
            Examify Administrator Documentation — Restricted to Authorized Academic Personnel.
        </footer>

        <script>
            // Search filter across subsections
            const searchInput = document.getElementById("searchInput");
            const allSubsections = Array.from(document.querySelectorAll(".subsection"));
            const noResults = document.getElementById("noResults");
            if (searchInput) {
                searchInput.addEventListener("input", () => {
                    const q = searchInput.value.trim().toLowerCase();
                    let visibleCount = 0;
                    allSubsections.forEach((sec) => {
                        const text = sec.textContent.toLowerCase();
                        const match = q === "" || text.includes(q);
                        sec.classList.toggle("filtered-out", !match);
                        if (match) {
                            visibleCount++;
                            if (q !== "") sec.open = true;
                        }
                    });
                    if (noResults) {
                        noResults.style.display = visibleCount === 0 ? "block" : "none";
                    }
                });
            }

            // Active TOC highlighting on scroll
            const tocLinks = document.querySelectorAll(".toc-link");
            const targets = Array.from(tocLinks)
                .map((l) => document.querySelector(l.getAttribute("href")))
                .filter(Boolean);
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        const id = "#" + entry.target.id;
                        const link = document.querySelector('.toc-link[href="' + id + '"]');
                        if (!link) return;
                        if (entry.isIntersecting) {
                            tocLinks.forEach((l) => l.classList.remove("active"));
                            link.classList.add("active");
                        }
                    });
                },
                { rootMargin: "-20% 0px -70% 0px", threshold: 0 }
            );
            targets.forEach((t) => observer.observe(t));

            // Copy JSON buttons
            document.querySelectorAll("[data-copy]").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const pre = btn.parentElement.querySelector("pre");
                    const text = pre.textContent;
                    navigator.clipboard.writeText(text).then(() => {
                        const old = btn.textContent;
                        btn.textContent = "Copied!";
                        setTimeout(() => {
                            btn.textContent = old;
                        }, 1400);
                    });
                });
            });
        </script>
    </body>
</html>
