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
<html lang="en" data-theme="dark">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Examify — Administrator Documentation</title>
        <!-- Self-contained typography with zero-CDN system fallbacks -->
        <style>
            :root {
                --bg: #0b0f1a;
                --panel: #121828;
                --panel-soft: #0f1422;
                --ink: #e9ecf4;
                --ink-soft: #94a0b8;
                --ink-faint: #5c6784;
                --rule: #232b40;
                --rule-strong: #34405c;
                --blue: #4c8dfa;
                --blue-deep: #3b76e0;
                --blue-soft: rgba(76,141,250,.14);
                --gold: #ffd700;
                --red: #f0665a;
                --green: #3ecf8e;
                --shadow: 0 12px 35px rgba(0,0,0,.28);
                --radius: 12px;
            }

            [data-theme="light"] {
                --bg: #f4f7fb;
                --panel: #fff;
                --panel-soft: #f8fafc;
                --ink: #10192c;
                --ink-soft: #4a5568;
                --ink-faint: #8a97ad;
                --rule: #e2e8f0;
                --rule-strong: #cbd5e1;
                --blue: #2563eb;
                --blue-deep: #1d4ed8;
                --blue-soft: rgba(37,99,235,.09);
                --gold: #b45309;
                --red: #dc2626;
                --green: #16a34a;
                --shadow: 0 12px 30px rgba(15,23,42,.08);
            }

            * { box-sizing: border-box; }
            html { scroll-behavior: smooth; scroll-padding-top: 82px; }
            body {
                margin: 0;
                background:
                    radial-gradient(circle at 15% 0%, var(--blue-soft), transparent 30%),
                    var(--bg);
                color: var(--ink);
                font-family: "IBM Plex Sans", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 16px;
                line-height: 1.6;
            }
            body::before {
                content: "";
                position: fixed;
                inset: 0;
                pointer-events: none;
                background-image: linear-gradient(rgba(128,128,128,.025) 1px, transparent 1px),
                                  linear-gradient(90deg, rgba(128,128,128,.025) 1px, transparent 1px);
                background-size: 32px 32px;
                mask-image: linear-gradient(to bottom, black, transparent 75%);
            }
            ::selection { background: var(--blue); color: #fff; }
            h1,h2,h3,h4 { color: var(--ink); letter-spacing: -.015em; margin: 0 0 .5em; }
            code,pre,.mono { font-family: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
            code {
                background: var(--rule); border: 1px solid var(--rule-strong);
                border-radius: 5px; padding: 1px 6px; color: var(--ink); font-size: .88em;
            }
            a { color: var(--blue); text-decoration: none; }
            a:hover { text-decoration: none; }

            .topbar {
                position: sticky; top: 0; z-index: 100;
                background: color-mix(in srgb, var(--panel) 88%, transparent);
                border-bottom: 1px solid var(--rule);
                backdrop-filter: blur(16px);
            }
            .progress-track {
                position: absolute; left: 0; bottom: -1px; height: 3px; width: 100%;
                background: transparent;
            }
            .progress-bar {
                height: 100%; width: 0; background: linear-gradient(90deg,var(--blue),#8b5cf6,var(--green));
                transition: width .15s ease;
            }
            .topbar-inner {
                max-width: 1380px; margin: auto; display: flex; align-items: center;
                gap: 14px; padding: 11px 22px;
            }
            .brand {
                display:flex; align-items:center; gap:10px; color:var(--ink);
                font-weight:800; white-space:nowrap;
            }
            .brand .logo-mark {
                width:34px; height:34px; border-radius:10px; display:flex; align-items:center;
                justify-content:center; background:linear-gradient(135deg,var(--blue),#7c3aed);
                box-shadow:0 7px 22px var(--blue-soft);
            }
            .brand .logo-mark svg { width:18px; stroke:#fff; }
            .badge-doc-type {
                font-family:"IBM Plex Mono",monospace; font-size:.68rem;
                border:1px solid var(--rule); background:var(--panel-soft); color:var(--ink-soft);
                border-radius:999px; padding:5px 10px; white-space:nowrap;
            }

            .search-wrap { position:relative; flex:1; max-width:500px; margin-left:auto; }
            .search-wrap input {
                width:100%; background:var(--panel-soft); border:1px solid var(--rule);
                color:var(--ink); padding:9px 76px 9px 38px; border-radius:999px;
                font-size:.85rem; outline:none; transition:.2s;
            }
            .search-wrap input:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-soft); }
            .search-wrap svg { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--ink-faint); }
            .search-count {
                position:absolute; right:10px; top:50%; transform:translateY(-50%);
                color:var(--ink-faint); font:11px "IBM Plex Mono",monospace;
            }
            .topbar-actions {
                display:flex; align-items:center; gap:7px; margin-left:0;
            }
            .topbar-actions .topbar-btn {
                background:transparent;
                border-color:transparent;
                color:var(--ink-soft);
                box-shadow:none;
            }
            .topbar-actions .topbar-btn:hover {
                background:var(--blue-soft);
                border-color:transparent;
                color:var(--blue);
            }
            .icon-btn,.topbar-btn {
                min-height:38px; display:inline-flex; align-items:center; justify-content:center;
                gap:6px; border:1px solid var(--rule); background:var(--panel-soft);
                color:var(--ink); border-radius:9px; padding:7px 11px; cursor:pointer;
                font-size:.8rem; font-weight:700;
            }
            .icon-btn:hover,.topbar-btn:hover { border-color:var(--blue); transform:translateY(-1px); }
            .topbar-btn-primary { background:var(--blue); color:#fff; border-color:var(--blue); }
            .theme-toggle { width:40px; padding:0; border-radius:50%; }
            [data-theme="dark"] .icon-sun,[data-theme="light"] .icon-moon { display:none; }

            .shell { max-width:1380px; margin:auto; display:grid; grid-template-columns:275px minmax(0,1fr); }
            .sidebar {
                position:sticky; top:61px; align-self:start; height:calc(100vh - 61px);
                overflow-y:auto; padding:24px 15px 50px 22px; border-right:1px solid var(--rule);
            }
            .sidebar-head { display:flex; justify-content:space-between; align-items:center; margin:0 4px 12px; }
            .sidebar-head span { color:var(--ink-faint); font:700 .67rem "IBM Plex Mono",monospace; text-transform:uppercase; letter-spacing:.1em; }
            .sidebar-actions button {
                border:0; background:transparent; color:var(--ink-soft); cursor:pointer; font-size:.7rem;
            }
            .toc-group { margin-bottom:20px; }
            .toc-role-label {
                font:700 .68rem "IBM Plex Mono",monospace; letter-spacing:.1em; text-transform:uppercase;
                color:var(--ink-faint); margin:0 0 6px 4px;
            }
            .toc-group ul { list-style:none; margin:0; padding:0; }
            .toc-link {
                display:block; padding:7px 10px; border-radius:8px; color:var(--ink-soft);
                font-size:.84rem; border-left:2px solid transparent; transition:.18s;
            }
            .toc-link:hover { background:var(--panel-soft); color:var(--ink); }
            .toc-link.active {
                background:var(--blue-soft); color:var(--blue); border-left-color:var(--blue); font-weight:700;
            }
            .toc-group ul ul { margin-left:10px; border-left:1px dashed var(--rule); padding-left:4px; }

            main { padding:0 42px 110px; min-width:0; }
            .hero {
                padding:54px 0 38px; border-bottom:1px solid var(--rule);
                display:grid; grid-template-columns:1.3fr 1fr; gap:34px; align-items:center;
            }
            .hero-eyebrow { font:700 .72rem "IBM Plex Mono",monospace; letter-spacing:.12em; text-transform:uppercase; color:var(--blue); margin-bottom:9px; }
            .hero h1 { font-size:clamp(2.2rem,4vw,3.4rem); line-height:1.05; margin-bottom:13px; }
            .hero p.lede { color:var(--ink-soft); max-width:700px; font-size:1.03rem; }
            .hero-stats { display:flex; flex-wrap:wrap; gap:12px; margin-top:22px; }
            .hero-stat {
                min-width:135px; padding:12px 14px; background:var(--panel);
                border:1px solid var(--rule); border-radius:11px;
            }
            .hero-stat .num { font-size:1.35rem; font-weight:800; }
            .hero-stat .label { font-size:.68rem; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.05em; }
            .admin-kpi-panel {
                background:linear-gradient(145deg,var(--panel),var(--panel-soft));
                border:1px solid var(--rule); border-radius:16px; box-shadow:var(--shadow); padding:22px;
            }
            .admin-kpi-panel h3 { margin-bottom:5px; }
            .admin-kpi-panel p { color:var(--ink-soft); font-size:.88rem; }
            .kpi-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
            .kpi-box { padding:14px; background:var(--panel); border:1px solid var(--rule); border-radius:10px; }
            .kpi-box .val { font-size:1.35rem; font-weight:900; color:var(--blue); }
            .kpi-box .title { color:var(--ink-soft); font:700 .64rem "IBM Plex Mono",monospace; text-transform:uppercase; }

            section.doc-section { padding-top:48px; scroll-margin-top:72px; }
            .section-kicker { font:700 .7rem "IBM Plex Mono",monospace; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.1em; }
            section.doc-section h2 { font-size:1.8rem; padding-bottom:12px; border-bottom:2px solid var(--rule); margin-bottom:20px; }

            .subsection {
                margin:0 0 14px; background:var(--panel); border:1px solid var(--rule);
                border-radius:12px; box-shadow:0 7px 25px rgba(0,0,0,.08); overflow:hidden;
                scroll-margin-top:80px; transition:border-color .2s, transform .2s, opacity .2s;
            }
            .subsection:hover { border-color:var(--rule-strong); }
            .subsection.filtered-out { display:none !important; }
            .subsection.search-hit { border-color:var(--blue); box-shadow:0 0 0 2px var(--blue-soft); }
            .subsection > summary {
                list-style:none; cursor:pointer; padding:15px 18px; display:flex; align-items:center;
                justify-content:space-between; gap:12px; font-weight:700; user-select:none;
            }
            .subsection > summary::-webkit-details-marker { display:none; }
            .summary-main { display:flex; align-items:center; gap:9px; min-width:0; }
            .summary-number {
                width:28px; height:28px; border-radius:8px; display:grid; place-items:center;
                background:var(--blue-soft); color:var(--blue); font:700 .68rem "IBM Plex Mono",monospace; flex:none;
            }
            .subsection > summary .arrow { color:var(--ink-soft); transition:.2s; }
            .subsection[open] > summary .arrow { transform:rotate(90deg); color:var(--blue); }
            .subsection > summary:hover { background:var(--panel-soft); }
            .subsection .body { padding:0 20px 20px; color:var(--ink-soft); font-size:.95rem; }
            .subsection .body p,.subsection .body ul,.subsection .body ol { margin-top:0; margin-bottom:12px; }
            .subsection .body strong { color:var(--ink); }

            table { width:100%; border-collapse:collapse; margin:10px 0; font-size:.9rem; }
            th,td { text-align:left; padding:9px 10px; border-bottom:1px solid var(--rule); }
            th { font:700 .68rem "IBM Plex Mono",monospace; text-transform:uppercase; color:var(--ink-soft); background:var(--panel-soft); }

            .admonition { border-left:4px solid var(--blue); background:var(--blue-soft); padding:12px 14px; border-radius:0 8px 8px 0; margin:12px 0; }
            .admonition.warning { border-left-color:var(--red); background:rgba(240,102,90,.08); }
            .admonition .adm-label { display:block; color:var(--blue); font:700 .68rem "IBM Plex Mono",monospace; text-transform:uppercase; margin-bottom:4px; }
            .admonition.warning .adm-label { color:var(--red); }

            .code-block { position:relative; background:#090d16; border:1px solid var(--rule); border-radius:9px; padding:15px; overflow:auto; margin:10px 0; }
            [data-theme="light"] .code-block { background:#101827; }
            .code-block pre { color:#dbe5f5; font-size:.82rem; padding-right:70px; }
            .code-block .copy-btn {
                position:absolute; top:8px; right:8px; background:var(--panel); color:var(--ink);
                border:1px solid var(--rule); border-radius:6px; padding:5px 9px; cursor:pointer; font:700 .68rem "IBM Plex Mono",monospace;
            }
            .code-block .copy-btn:hover { border-color:var(--blue); }

            .badge { font:700 .65rem "IBM Plex Mono",monospace; padding:3px 8px; border-radius:999px; text-transform:uppercase; }
            .badge.live { background:rgba(22,163,74,.12); color:var(--green); }
            .badge.scheduled { background:var(--blue-soft); color:var(--blue); }
            .badge.ended { background:var(--panel-soft); color:var(--ink-soft); border:1px solid var(--rule); }
            .role-pill { font:700 .62rem "IBM Plex Mono",monospace; text-transform:uppercase; padding:3px 7px; border-radius:8px; margin-left:5px; background:var(--blue-soft); color:var(--blue); }

            .section-nav {
                display:flex; justify-content:space-between; gap:12px; margin-top:26px; padding-top:18px;
                border-top:1px solid var(--rule);
            }
            .section-nav button {
                border:1px solid var(--rule); background:var(--panel); color:var(--ink);
                padding:10px 14px; border-radius:9px; cursor:pointer; font-weight:700;
            }
            .section-nav button:hover { border-color:var(--blue); color:var(--blue); }

            .no-results { display:none; padding:45px; text-align:center; color:var(--ink-soft); font:700 .8rem "IBM Plex Mono",monospace; }
            .search-highlight { background:rgba(255,215,0,.28); color:inherit; border-radius:3px; padding:0 2px; }
            .toast {
                position:fixed; right:22px; bottom:22px; z-index:200; max-width:330px;
                background:var(--panel); color:var(--ink); border:1px solid var(--rule);
                box-shadow:var(--shadow); padding:12px 15px; border-radius:10px;
                transform:translateY(20px); opacity:0; pointer-events:none; transition:.25s;
                font-size:.85rem;
            }
            .toast.show { transform:translateY(0); opacity:1; }
            .back-top {
                position:fixed; right:22px; bottom:72px; z-index:90; width:42px; height:42px;
                border-radius:50%; border:1px solid var(--rule); background:var(--panel);
                color:var(--ink); cursor:pointer; opacity:0; transform:translateY(10px); pointer-events:none; transition:.2s;
                box-shadow:var(--shadow);
            }
            .back-top.visible { opacity:1; transform:none; pointer-events:auto; }
            .shortcut-hint { font:10px "IBM Plex Mono",monospace; color:var(--ink-faint); margin-left:4px; }

            footer { border-top:1px solid var(--rule); padding:25px; text-align:center; color:var(--ink-faint); font-size:.8rem; }

            @media (max-width:1000px) {
                .topbar-inner { flex-wrap:wrap; }
                .search-wrap { order:10; max-width:none; flex-basis:100%; margin:0; }
                .shell { grid-template-columns:1fr; }
                .sidebar { position:relative; top:auto; height:auto; max-height:330px; border-right:0; border-bottom:1px solid var(--rule); }
                .hero { grid-template-columns:1fr; }
                main { padding:0 20px 80px; }
            }
            @media (max-width:650px) {
                .badge-doc-type,.topbar-btn-secondary { display:none; }
                .topbar-inner { padding:9px 12px; }
                main { padding:0 14px 70px; }
                .hero { padding-top:35px; }
                .hero h1 { font-size:2.15rem; }
                .kpi-grid { grid-template-columns:1fr; }
                .hero-stats { display:grid; grid-template-columns:1fr 1fr; }
                .hero-stat { min-width:0; }
                th,td { padding:7px; }
                .section-nav { flex-direction:column; }
            }
            @media (prefers-reduced-motion: reduce) {
                *,html { scroll-behavior:auto !important; transition-duration:0.01ms !important; animation-duration:0.01ms !important; }
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
            <div class="topbar-inner">
                <a href="../../admin/admin-dashboard.php" class="brand">
                    <span class="logo-mark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    Examify Docs
                </a>
                <span class="badge-doc-type">Admin Guide</span>

                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input id="searchInput" type="text" placeholder="Search workflows... (Ctrl + K)" aria-label="Search documentation" />
                </div>

                <div class="topbar-actions">
                    <button class="theme-toggle icon-btn" id="themeToggle" title="Toggle Light/Dark Mode" aria-label="Toggle theme">
                        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </button>
                    <a href="../../admin/admin-dashboard.php" class="topbar-btn topbar-btn-secondary">Portal Home</a>
                    <a href="user-doc.php" class="topbar-btn topbar-btn-primary">User Docs →</a>
                </div>
            </div>
        </div>

        <div class="shell">
            <nav class="sidebar" id="sidebar">
                <div class="sidebar-head">
                    <span>Documentation</span>
                    <div class="sidebar-actions">
                        <button type="button" id="expandNav">Expand</button>
                        <button type="button" id="collapseNav">Collapse</button>
                    </div>
                </div>
                <div class="toc-group">
                    <div class="toc-role-label">Overview</div>
                    <ul>
                        <li><a class="toc-link top" href="#sec-1">1. Institutional RBAC</a></li>
                    </ul>
                </div>

                <div class="toc-group">
                    <div class="toc-role-label">Instructor Manual</div>
                    <ul>
                        <li>
                            <a class="toc-link top" href="#sec-3">3. Admin Workflows</a>
                            <ul>
                                <li><a class="toc-link" href="#sec-3-1">3.1 Authentication</a></li>
                                <li><a class="toc-link" href="#sec-3-2">3.2 Students</a></li>
                                <li><a class="toc-link" href="#sec-3-3">3.3 Promotions</a></li>
                                <li><a class="toc-link" href="#sec-3-4">3.4 Question Banks</a></li>
                                <li><a class="toc-link" href="#sec-3-5">3.5 Exam Control</a></li>
                                <li><a class="toc-link" href="#sec-3-6">3.6 Live Proctoring</a></li>
                                <li><a class="toc-link" href="#sec-3-7">3.7 Results & PDFs</a></li>
                                <li><a class="toc-link" href="#sec-3-8">3.8 Teacher Provisioning</a></li>
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
                            Comprehensive manual for managing curriculum questions, live exams, anti-cheat proctoring, rosters, and secure institutional exports.
                        </p>
                        <div class="hero-stats">
                            <div class="hero-stat">
                                <div class="num"><?= $isSuper ? 'Superadmin' : 'Teacher' ?></div>
                                <div class="label">Permission Level</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">15</div>
                                <div class="label">Core Workflows</div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-kpi-panel">
                        <h3>Campus Exam Engine</h3>
                        <p>Real-time laboratory controls, anti-cheat surveillance, and resilient session management.</p>
                        <div class="kpi-grid">
                            <div class="kpi-box">
                                <div class="val">Zero</div>
                                <div class="title">External CDN Dependencies</div>
                            </div>
                            <div class="kpi-box">
                                <div class="val">5 Sec</div>
                                <div class="title">Proctor Refresh Rate</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1 -->
                <section class="doc-section" id="sec-1">
                    <div class="section-kicker">Governance & Authority</div>
                    <h2>1. Institutional Role-Based Access Control</h2>
                    <details class="subsection" open>
                        <summary>Administrative Permissions Matrix<span class="arrow">▸</span></summary>
                        <div class="body">
                            <table>
                                <tr>
                                    <th>Feature / Module</th>
                                    <th>Teacher</th>
                                    <th>Superadmin</th>
                                </tr>
                                <tr>
                                    <td>Manage Subjects & Question Banks</td>
                                    <td>✅ Full</td>
                                    <td>✅ Full</td>
                                </tr>
                                <tr>
                                    <td>Create & Control Examinations</td>
                                    <td>✅ Full</td>
                                    <td>✅ Full</td>
                                </tr>
                                <tr>
                                    <td>Live Proctoring & Emergency Unlocks</td>
                                    <td>✅ Full</td>
                                    <td>✅ Full</td>
                                </tr>
                                <tr>
                                    <td>Provision or Retire Teachers</td>
                                    <td>❌ Restricted</td>
                                    <td>✅ Exclusive</td>
                                </tr>
                            </table>
                        </div>
                    </details>
                </section>

                <!-- SECTION 3 -->
                <section class="doc-section" id="sec-3">
                    <div class="section-kicker">Faculty Operational Manual</div>
                    <h2>3. Administrator Portal Guide <span class="role-pill">Admin</span></h2>

                    <details class="subsection" open id="sec-3-1">
                        <summary>3.1 Authentication & Singleton Sessions<span class="arrow">▸</span></summary>
                        <div class="body">
                            <ol>
                                <li>Navigate to <code>admin/admin-login.php</code>.</li>
                                <li>Provide your credential set. Active singleton enforcement ensures active tokens invalidate older sessions on duplicate login attempts.</li>
                            </ol>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-2">
                        <summary>3.2 Student Management & Directory Actions<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Manage accounts seamlessly via filters (Department, Semester, Status). Reset credentials or suspend user access instantly.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-3">
                        <summary>3.3 Bulk Student Promotion<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Advance cohorts by +1 semester automatically, bounded safely at Semester 8 caps.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-4">
                        <summary>3.4 Question Banks & Batch CSV Import<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Supports 5 item types: <code>single</code>, <code>multiple</code>, <code>case_study</code>, <code>assertion_reason</code>, and <code>matching</code>.</p>
                            <div class="code-block">
                                <button class="copy-btn" data-copy>Copy</button>
                                <pre style="margin:0; white-space:pre-wrap">Question Text,Unit,Option A,Option B,Option C,Option D,Correct,Type
"What is an OS?",1,"System software","Application","Hardware","Malicious",A,single</pre>
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-5">
                        <summary>3.5 Exam Control & Live Timers<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Toggle exams between Scheduled, Live, and Ended states. Trigger instant emergency time extensions (+5m/+10m) on demand.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-6">
                        <summary>3.6 Live Classroom Proctoring Panel<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Monitor cheating telemetry (tab switches, window blurs). Candidates triggering 3 violations are automatically disqualified by the engine.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-7">
                        <summary>3.7 Results & PDF Downloads<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Generate clean, professional institutional grade reports complete with podium ranks and signature blocks via the native PDF generator.</p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-3-8">
                        <summary>3.8 Teacher Provisioning & Record Retention<span class="arrow">▸</span></summary>
                        <div class="body">
                            <p>Superadmins can provision staff accounts. When staff members retire, their historic exam and question records remain safely anchored as <code>[Retired]</code>.</p>
                        </div>
                    </details>
                </section>

                <div class="no-results" id="noResults">
                    No matching administrative topics found. Try refining your keywords.
                </div>
            </main>
        </div>

        <footer>
            Examify Administrator Documentation — Restricted to Authorized Academic Personnel.
        </footer>

        <script>
            (() => {
                "use strict";

                const root = document.documentElement;
                const themeToggle = document.getElementById("themeToggle");
                const searchInput = document.getElementById("searchInput");
                const progressBar = document.getElementById("progressBar");
                const expandNav = document.getElementById("expandNav");
                const collapseNav = document.getElementById("collapseNav");
                const noResults = document.getElementById("noResults");
                const subsections = [...document.querySelectorAll(".subsection")];
                const tocLinks = [...document.querySelectorAll(".toc-link")];
                const sections = [...document.querySelectorAll("section.doc-section")];

                function showToast(message) {
                    let toast = document.getElementById("toast");
                    if (!toast) {
                        toast = document.createElement("div");
                        toast.id = "toast";
                        toast.className = "toast";
                        document.body.appendChild(toast);
                    }
                    toast.textContent = message;
                    toast.classList.add("show");
                    clearTimeout(window.__examifyToastTimer);
                    window.__examifyToastTimer = setTimeout(() => toast.classList.remove("show"), 1800);
                }

                // Theme persistence
                const savedTheme = localStorage.getItem("examify_admin_theme") || "dark";
                root.setAttribute("data-theme", savedTheme);

                themeToggle?.addEventListener("click", () => {
                    const next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
                    root.setAttribute("data-theme", next);
                    localStorage.setItem("examify_admin_theme", next);
                    showToast(next === "dark" ? "Dark mode enabled" : "Light mode enabled");
                });

                // Search with result count, auto-open, and temporary highlighting.
                const searchCount = document.createElement("span");
                searchCount.className = "search-count";
                searchCount.textContent = "0";
                document.querySelector(".search-wrap")?.appendChild(searchCount);

                function clearHighlights() {
                    document.querySelectorAll(".search-highlight").forEach(mark => {
                        mark.replaceWith(document.createTextNode(mark.textContent));
                    });
                }

                function highlightText(element, query) {
                    if (!query) return;
                    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
                    const nodes = [];
                    while (walker.nextNode()) {
                        const node = walker.currentNode;
                        if (node.parentElement?.closest(".search-highlight,button,script,style")) continue;
                        if (node.nodeValue.toLowerCase().includes(query)) nodes.push(node);
                    }
                    nodes.forEach(node => {
                        const frag = document.createDocumentFragment();
                        const value = node.nodeValue;
                        const lower = value.toLowerCase();
                        let cursor = 0;
                        let index;
                        while ((index = lower.indexOf(query, cursor)) !== -1) {
                            frag.appendChild(document.createTextNode(value.slice(cursor, index)));
                            const mark = document.createElement("mark");
                            mark.className = "search-highlight";
                            mark.textContent = value.slice(index, index + query.length);
                            frag.appendChild(mark);
                            cursor = index + query.length;
                        }
                        frag.appendChild(document.createTextNode(value.slice(cursor)));
                        node.replaceWith(frag);
                    });
                }

                function runSearch() {
                    const q = searchInput.value.trim().toLowerCase();
                    clearHighlights();
                    let visible = 0;

                    subsections.forEach((sec) => {
                        const match = !q || sec.textContent.toLowerCase().includes(q);
                        sec.classList.toggle("filtered-out", !match);
                        sec.classList.toggle("search-hit", Boolean(q && match));
                        if (match) {
                            visible++;
                            if (q) sec.open = true;
                        }
                        if (q && match) highlightText(sec, q);
                    });

                    searchCount.textContent = q ? String(visible) : String(subsections.length);
                    if (noResults) noResults.style.display = visible ? "none" : "block";

                    if (q && visible === 1) {
                        const target = subsections.find(s => !s.classList.contains("filtered-out"));
                        target?.scrollIntoView({ behavior: "smooth", block: "center" });
                    }
                }

                searchInput?.addEventListener("input", runSearch);

                // Keyboard shortcut: Ctrl/Cmd + K
                document.addEventListener("keydown", (event) => {
                    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
                        event.preventDefault();
                        searchInput?.focus();
                        searchInput?.select();
                    }
                    if (event.key === "Escape" && document.activeElement === searchInput) {
                        searchInput.value = "";
                        runSearch();
                        searchInput.blur();
                    }
                });

                expandNav?.addEventListener("click", () => document.querySelectorAll(".sidebar .toc-group ul ul").forEach(x => x.style.display = "block"));
                collapseNav?.addEventListener("click", () => document.querySelectorAll(".sidebar .toc-group ul ul").forEach(x => x.style.display = "none"));

                // Remember which subsections are open for this page.
                const openStateKey = "examify_admin_open_sections";
                let savedOpen = [];
                try { savedOpen = JSON.parse(sessionStorage.getItem(openStateKey) || "[]"); } catch (_) {}
                subsections.forEach(sec => {
                    if (sec.id && savedOpen.includes(sec.id)) sec.open = true;
                    sec.addEventListener("toggle", () => {
                        const current = subsections.filter(s => s.open && s.id).map(s => s.id);
                        try { sessionStorage.setItem(openStateKey, JSON.stringify(current)); } catch (_) {}
                    });
                });

                // Add accessible numbering to summary rows.
                subsections.forEach((sec, index) => {
                    const summary = sec.querySelector("summary");
                    if (!summary || summary.querySelector(".summary-main")) return;
                    const arrow = summary.querySelector(".arrow");
                    const label = document.createElement("span");
                    label.className = "summary-main";
                    const num = document.createElement("span");
                    num.className = "summary-number";
                    num.textContent = String(index + 1).padStart(2, "0");
                    const textNodes = [...summary.childNodes].filter(n =>
                        n.nodeType === Node.TEXT_NODE && n.textContent.trim()
                    );
                    textNodes.forEach(n => label.appendChild(n.cloneNode(true)));
                    textNodes.forEach(n => n.remove());
                    label.prepend(num);
                    summary.insertBefore(label, arrow || null);
                });

                // Copy buttons with toast feedback.
                document.querySelectorAll("[data-copy]").forEach(btn => {
                    btn.addEventListener("click", async () => {
                        const pre = btn.parentElement?.querySelector("pre");
                        if (!pre) return;
                        try {
                            await navigator.clipboard.writeText(pre.textContent);
                            btn.textContent = "Copied ✓";
                            showToast("Code copied to clipboard");
                            setTimeout(() => btn.textContent = "Copy", 1500);
                        } catch (_) {
                            btn.textContent = "Select";
                            showToast("Clipboard access unavailable — select the code manually");
                            setTimeout(() => btn.textContent = "Copy", 1500);
                        }
                    });
                });

                // Scroll progress.
                function updateProgress() {
                    const max = document.documentElement.scrollHeight - window.innerHeight;
                    const percent = max > 0 ? Math.min(100, Math.max(0, window.scrollY / max * 100)) : 0;
                    if (progressBar) progressBar.style.width = percent + "%";
                }

                // Active sidebar link based on current section.
                function updateActiveNav() {
                    const marker = window.scrollY + 110;
                    let currentId = sections[0]?.id || "";
                    sections.forEach(section => {
                        if (section.offsetTop <= marker) currentId = section.id;
                    });
                    tocLinks.forEach(link => {
                        link.classList.toggle("active", link.getAttribute("href") === "#" + currentId);
                    });
                }

                // Back to top.
                const backTop = document.createElement("button");
                backTop.type = "button";
                backTop.className = "back-top";
                backTop.setAttribute("aria-label", "Back to top");
                backTop.textContent = "↑";
                document.body.appendChild(backTop);
                backTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

                function onScroll() {
                    updateProgress();
                    updateActiveNav();
                    backTop.classList.toggle("visible", window.scrollY > 450);
                }
                window.addEventListener("scroll", onScroll, { passive: true });
                onScroll();

                // Section navigation buttons.
                sections.forEach((section, index) => {
                    const nav = document.createElement("div");
                    nav.className = "section-nav";
                    const previous = document.createElement("button");
                    const next = document.createElement("button");
                    previous.type = next.type = "button";
                    previous.textContent = index === 0 ? "↑ Back to top" : "← Previous section";
                    next.textContent = index === sections.length - 1 ? "End of guide" : "Next section →";
                    previous.disabled = false;
                    next.disabled = index === sections.length - 1;
                    previous.addEventListener("click", () => {
                        if (index === 0) window.scrollTo({ top: 0, behavior: "smooth" });
                        else sections[index - 1].scrollIntoView({ behavior: "smooth", block: "start" });
                    });
                    next.addEventListener("click", () => {
                        if (index < sections.length - 1) sections[index + 1].scrollIntoView({ behavior: "smooth", block: "start" });
                    });
                    nav.append(previous, next);
                    section.appendChild(nav);
                });

                // Animated numeric KPI values.
                document.querySelectorAll(".hero-stat .num").forEach(el => {
                    const raw = el.textContent.trim();
                    const match = raw.match(/^(\d+)$/);
                    if (!match) return;
                    const target = Number(match[1]);
                    let value = 0;
                    const step = Math.max(1, Math.ceil(target / 25));
                    const timer = setInterval(() => {
                        value = Math.min(target, value + step);
                        el.textContent = String(value);
                        if (value >= target) clearInterval(timer);
                    }, 35);
                });
            })();
        </script>
    </body>
</html>