<?php
/**
 * Examify — User & Student Documentation
 * Publicly accessible by all users, students, candidates, and visitors.
 */
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Examify — User Documentation</title>
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
                background: rgba(255, 255, 255, 0.12);
                color: #cfc9b4;
                padding: 4px 10px;
                border-radius: 12px;
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
                border-left: 2px solid var(--blue);
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
                color: var(--blue-deep);
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

            /* signature: exam palette demo card */
            .palette-card {
                background: var(--paper-panel);
                border: 1px solid var(--rule);
                border-radius: 6px;
                box-shadow: var(--shadow);
                padding: 22px;
            }
            .palette-card .pc-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 14px;
            }
            .palette-card .pc-title {
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.78rem;
                color: var(--ink-soft);
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .palette-card .pc-timer {
                font-family: "IBM Plex Mono", monospace;
                font-weight: 600;
                font-size: 0.95rem;
                background: var(--ink);
                color: var(--gold);
                padding: 3px 10px;
                border-radius: 4px;
            }
            .palette-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 6px;
                margin-bottom: 16px;
            }
            .palette-grid .cell {
                aspect-ratio: 1;
                border-radius: 3px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: "IBM Plex Mono", monospace;
                font-size: 0.7rem;
                font-weight: 600;
                color: #fff;
                cursor: default;
                transition: transform 0.15s;
            }
            .palette-grid .cell:hover {
                transform: scale(1.12);
            }
            .palette-grid .cell.answered { background: var(--blue); }
            .palette-grid .cell.review { background: var(--gold); color: #3a2a05; }
            .palette-grid .cell.unanswered { background: var(--gray-box); }
            .palette-grid .cell.current {
                outline: 2px solid var(--ink);
                outline-offset: 2px;
            }
            .palette-legend {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                font-size: 0.78rem;
                color: var(--ink-soft);
            }
            .palette-legend span {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .palette-legend i {
                width: 10px;
                height: 10px;
                border-radius: 2px;
                display: inline-block;
            }
            .leg-answered { background: var(--blue); }
            .leg-review { background: var(--gold); }
            .leg-unanswered { background: var(--gray-box); }

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

            .kbd {
                font-family: "IBM Plex Mono", monospace;
                background: #fff;
                border: 1px solid var(--rule-strong);
                border-bottom-width: 2px;
                padding: 1px 6px;
                border-radius: 4px;
                font-size: 0.82rem;
            }

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
            .role-pill.student {
                background: #e6eef4;
                color: var(--blue-deep);
            }

            .admin-callout {
                background: #faf6ea;
                border: 1px solid #ebd9a9;
                border-radius: 6px;
                padding: 20px;
                margin-top: 40px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
            }
            .admin-callout-content h3 {
                margin: 0 0 6px 0;
                font-size: 1.15rem;
                color: var(--gold-deep);
            }
            .admin-callout-content p {
                margin: 0;
                font-size: 0.92rem;
                color: var(--ink-soft);
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
                .admin-callout { flex-direction: column; align-items: flex-start; }
            }

            :focus-visible {
                outline: 2px solid var(--blue);
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
                <a href="../../index.php" class="brand">
                    <span class="dot-grid"><span></span><span></span><span></span></span>
                    Examify Docs
                </a>
                <span class="badge-doc-type">Student & Candidate Guide</span>
                
                <div class="search-wrap">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input id="searchInput" type="text" placeholder="Search topics…" />
                </div>

                <div class="topbar-links">
                    <a href="../../index.php" class="topbar-btn topbar-btn-secondary">Portal Home</a>
                    <a href="admin-doc.php" class="topbar-btn topbar-btn-gold" title="Restricted to Instructors and Administrators">
                        Admin Docs &rarr;
                    </a>
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
                        <li><a class="toc-link top" href="#sec-1">1. User Roles</a></li>
                    </ul>
                </div>

                <div class="toc-group">
                    <div class="toc-role-label">
                        <span class="chip" style="background: var(--blue)"></span>Student Portal
                    </div>
                    <ul>
                        <li>
                            <a class="toc-link top" href="#sec-2">2. Student Portal Guide</a>
                            <ul>
                                <li><a class="toc-link" href="#sec-2-1">2.1 Registration</a></li>
                                <li><a class="toc-link" href="#sec-2-2">2.2 Password Visibility</a></li>
                                <li><a class="toc-link" href="#sec-2-3">2.3 Student Login</a></li>
                                <li><a class="toc-link" href="#sec-2-4">2.4 Hardware & Touchscreen</a></li>
                                <li><a class="toc-link" href="#sec-2-5">2.5 Student Dashboard</a></li>
                                <li><a class="toc-link" href="#sec-2-6">2.6 Classroom Access PIN</a></li>
                                <li><a class="toc-link" href="#sec-2-7">2.7 Taking an Examination</a></li>
                                <li><a class="toc-link" href="#sec-2-8">2.8 Anti-Cheat Rules</a></li>
                                <li><a class="toc-link" href="#sec-2-9">2.9 Submitting Answers</a></li>
                                <li><a class="toc-link" href="#sec-2-10">2.10 Results & Scorecards</a></li>
                                <li><a class="toc-link" href="#sec-2-11">2.11 Profile & Requests</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <main>
                <div class="hero">
                    <div>
                        <div class="hero-eyebrow">Student & Candidate Handbook</div>
                        <h1>Examify</h1>
                        <p class="lede">
                            A secure, lightweight online examination platform designed for college computer laboratories and local area networks.
                            This guide explains how to register, take tests, observe proctoring rules, and review results.
                        </p>
                        <div class="hero-stats">
                            <div class="hero-stat">
                                <div class="num">2</div>
                                <div class="label">User roles</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">11</div>
                                <div class="label">Candidate workflows</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">3</div>
                                <div class="label">Violation limit</div>
                            </div>
                        </div>
                    </div>

                    <div class="palette-card">
                        <div class="pc-head">
                            <span class="pc-title">Question Palette — live demo</span>
                            <span class="pc-timer" id="demoTimer">29:58</span>
                        </div>
                        <div class="palette-grid" id="paletteGrid"></div>
                        <div class="palette-legend">
                            <span><i class="leg-answered"></i>Answered</span>
                            <span><i class="leg-review"></i>Marked for review</span>
                            <span><i class="leg-unanswered"></i>Not answered</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1 -->
                <section class="doc-section" id="sec-1">
                    <div class="section-kicker">Getting oriented</div>
                    <h2>1. User Roles</h2>
                    <details class="subsection" open>
                        <summary>
                            System User Personas<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                <strong>Student / Candidate:</strong> Enrolls in courses, unlocks scheduled examinations with classroom PINs, answers questions under timed conditions, and reviews academic scorecards.
                            </p>
                            <p>
                                <strong>Instructor / Administrator:</strong> Authors curriculum questions, controls active exam rooms, proctors live computer lab sessions, and reviews evaluation analytics.
                            </p>
                            <div class="admonition">
                                <span class="adm-label">Need Faculty Guidance?</span>
                                Instructors and Administrators can access the specialized <a href="admin-doc.php">Administrator Documentation</a> with active faculty credentials.
                            </div>
                        </div>
                    </details>
                </section>

                <!-- SECTION 2 -->
                <section class="doc-section" id="sec-2">
                    <div class="section-kicker">For Students & Candidates</div>
                    <h2>
                        2. Student Portal Guide
                        <span class="role-pill student">Student</span>
                    </h2>

                    <details class="subsection" open id="sec-2-1">
                        <summary>
                            2.1 Student Account Registration<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Follow these steps to register a new student account:</p>
                            <ol>
                                <li>Open your web browser and navigate to the application address.</li>
                                <li>Click the <strong>Student Portal</strong> button on the landing page.</li>
                                <li>Click the <strong>Register here</strong> link.</li>
                                <li>Type your full name in the <strong>Full Name</strong> field.</li>
                                <li>Type your college email in the <strong>College Email</strong> field.</li>
                                <li>
                                    Type your student ID in the <strong>Roll Number</strong> field.
                                    The system converts your input to uppercase letters automatically.
                                </li>
                                <li>Select your academic department (e.g., BCA, BBA) from the dropdown list.</li>
                                <li>Select your current semester (1 through 8) from the dropdown list.</li>
                                <li>Type a secure password (minimum 6 characters) in the <strong>Password</strong> field.</li>
                                <li>Re-enter the password in the <strong>Confirm Password</strong> field.</li>
                                <li>Click the <strong>Register</strong> button.</li>
                            </ol>
                            <p>
                                The system registers your account in <code>pending</code> status.
                                A progress bar displays for 30 seconds and redirects you to the homepage.
                                Your department instructor reviews and approves your account before examinations.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-2">
                        <summary>
                            2.2 Universal Password Visibility Toggle<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Every password input field on the portal includes an interactive eye icon:
                            </p>
                            <ul>
                                <li>Click the <strong>Eye Icon</strong> to view masked password text.</li>
                                <li>Click the <strong>Eye Icon</strong> again to re-mask password characters.</li>
                            </ul>
                            <div class="admonition">
                                <span class="adm-label">Security Reminder</span>
                                Always mask your password before beginning an examination if other students sit near your computer terminal.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-3">
                        <summary>
                            2.3 Student Login and Single-Device Session Rules<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Navigate to the student login page (<code>student/login.php</code>).</li>
                                <li>Enter your registered institutional email address.</li>
                                <li>Enter your account password.</li>
                                <li>Click the <strong>Login</strong> button.</li>
                            </ol>
                            <div class="admonition warning">
                                <span class="adm-label">Singleton Login Policy</span>
                                Examify allows only <strong>one active session per student</strong>. If you log into your account on a second device, your previous session terminates immediately to maintain exam integrity.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-4">
                        <summary>
                            2.4 Hardware Requirements & Touchscreen Gating<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Review these mandatory hardware policies before exam sessions:</p>
                            <ul>
                                <li><strong>Desktop or Laptop Required:</strong> You must take exams on a desktop or laptop computer. The exam room blocks mobile phones and tablets.</li>
                                <li><strong>Lockout Screen:</strong> Attempting to access an active examination on a phone or tablet displays a mobile lockout screen instructing you to switch to a computer.</li>
                                <li><strong>Touchscreen Suppression:</strong> If you use a touchscreen laptop, the examination room suppresses touch taps to prevent cheating gestures. You must interact using your laptop touchpad or a physical mouse.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-5">
                        <summary>
                            2.5 Student Dashboard & Live Exam Discovery<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                The Student Dashboard automatically discovers examinations tailored to your registered department and semester:
                            </p>
                            <ul>
                                <li><strong>Automatic Polling:</strong> The dashboard queries the server every 10 seconds in the background. When an instructor launches an examination, it appears without manual page reloads.</li>
                                <li><strong>Status Badges:</strong> Cards display <span class="badge live">Live</span> for active tests, <span class="badge scheduled">Scheduled</span> for upcoming quizzes, and <span class="badge ended">Ended</span> for closed exams.</li>
                                <li><strong>Assessment Details:</strong> Each card displays course subject, duration in minutes, total questions, and maximum marks.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-6">
                        <summary>
                            2.6 Unlocking an Exam with a Classroom PIN<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Instructors frequently assign a 4-digit PIN to surprise quizzes:
                            </p>
                            <ol>
                                <li>Click the <strong>Start Exam</strong> button on the active test card.</li>
                                <li>If the exam is PIN-protected, an authorization prompt appears.</li>
                                <li>Type the 4-digit whiteboard PIN announced by your instructor.</li>
                                <li>Click the <strong>Unlock Exam</strong> button to initialize your session.</li>
                            </ol>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-7">
                        <summary>
                            2.7 Taking an Examination (Interface Guide)<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click the <strong>Enter Fullscreen & Begin</strong> button.</li>
                                <li>The browser enters full-screen mode and starts the countdown timer.</li>
                                <li>Read the question text and select your answer option (A, B, C, or D).</li>
                                <li>The system saves your selected option automatically.</li>
                                <li>Click <strong>Next</strong> or <strong>Previous</strong> to step through questions sequentially.</li>
                                <li>Click <strong>Mark for Review</strong> if you wish to recheck an answer before final submission.</li>
                            </ol>
                            <h4>Question Palette Navigation</h4>
                            <p>
                                The right-hand sidebar displays your complete question grid. Click any question box to jump directly to that question.
                            </p>
                            <table>
                                <tr><th>Color</th><th>Meaning</th></tr>
                                <tr><td><strong style="color: var(--blue)">Blue</strong></td><td>Answer saved</td></tr>
                                <tr><td><strong style="color: var(--gold)">Yellow</strong></td><td>Marked for review</td></tr>
                                <tr><td><strong style="color: var(--gray-box)">Gray</strong></td><td>Not answered yet</td></tr>
                            </table>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-8">
                        <summary>
                            2.8 Anti-Cheat Security Rules & Cheating Infractions<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>Examify actively monitors examination room integrity:</p>
                            <ul>
                                <li>Do not exit full-screen mode.</li>
                                <li>Do not switch browser tabs or open other programs.</li>
                                <li>Do not minimize the examination window.</li>
                                <li>Do not press <span class="kbd">F12</span>, <span class="kbd">Ctrl+Shift+I</span>, or inspect developer tools.</li>
                            </ul>
                            <div class="admonition warning">
                                <span class="adm-label">Three-Strike Rule</span>
                                When an infraction occurs, the exam pauses and records an audit log. After <strong>3 violations</strong>, the system terminates your attempt and submits your answers immediately.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-9">
                        <summary>
                            2.9 Submitting Answers (In-DOM Confirmation Modal)<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Check your question palette to ensure all desired questions are answered.</li>
                                <li>Click the <strong>Submit Exam</strong> button in the action sidebar.</li>
                                <li>
                                    A custom in-DOM confirmation dialog opens on screen displaying live summary counters:
                                    <ul>
                                        <li><strong>Answered Questions:</strong> Count of completed items.</li>
                                        <li><strong>Marked for Review:</strong> Items flagged for double-checking.</li>
                                        <li><strong>Unanswered Questions:</strong> Items left blank.</li>
                                    </ul>
                                </li>
                                <li>Click <strong>Confirm & Submit</strong> to finalize your submission.</li>
                            </ol>
                            <p>
                                The proctoring monitor stops event listeners prior to form submission, preventing false window blur infractions.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-10">
                        <summary>
                            2.10 Reviewing Results & Downloading PDF Scorecards<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <p>
                                Immediately following submission, the evaluation page displays your performance metrics:
                            </p>
                            <ul>
                                <li>Total score achieved and maximum possible marks.</li>
                                <li>Percentage score with clearing evaluation (<span class="badge live">PASS</span> or <span class="badge ended">FAIL</span>).</li>
                                <li>Itemized breakdown of correct, incorrect, and skipped questions.</li>
                            </ul>
                            <p>
                                Click the <strong>Download Scorecard PDF</strong> button to generate an official institutional grade sheet.
                                The PDF includes candidate metadata, metric tables, and official signature endorsement lines.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-11">
                        <summary>
                            2.11 Student Profile & Academic Detail Update Requests<span class="arrow">▸</span>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click the <strong>Profile</strong> link in the navigation header.</li>
                                <li>Review your enrolled details and historical test submissions.</li>
                                <li>Click <strong>Edit Profile</strong> if your department or semester details need correction.</li>
                                <li>Enter the revised details and click <strong>Request Update</strong>.</li>
                            </ol>
                            <div class="admonition">
                                <span class="adm-label">Pending Verification</span>
                                Change requests require administrative approval before updates reflect across active test rosters.
                            </div>
                        </div>
                    </details>
                </section>

                <div class="admin-callout">
                    <div class="admin-callout-content">
                        <h3>Are you a Teacher or Administrator?</h3>
                        <p>Access the Instructor Portal Guide to manage subjects, question banks, classroom PINs, and real-time proctoring.</p>
                    </div>
                    <a href="admin-doc.php" class="topbar-btn topbar-btn-gold">Open Admin Documentation &rarr;</a>
                </div>

                <div class="no-results" id="noResults">
                    No matching documentation sections found. Try a different search term.
                </div>
            </main>
        </div>

        <footer>
            Examify User Documentation — Official Student & Candidate Handbook.
        </footer>

        <script>
            // Decorative palette grid
            const pattern = [
                "answered", "answered", "review", "unanswered",
                "answered", "unanswered", "review", "answered",
                "unanswered", "answered", "answered", "review",
                "unanswered", "unanswered", "answered", "review",
            ];
            const grid = document.getElementById("paletteGrid");
            if (grid) {
                pattern.forEach((s, i) => {
                    const c = document.createElement("div");
                    c.className = "cell " + s + (i === 9 ? " current" : "");
                    c.textContent = i + 1;
                    grid.appendChild(c);
                });
            }

            // Decorative countdown timer
            let secs = 29 * 60 + 58;
            const timerEl = document.getElementById("demoTimer");
            if (timerEl) {
                setInterval(() => {
                    secs = secs > 0 ? secs - 1 : 29 * 60 + 58;
                    const m = String(Math.floor(secs / 60)).padStart(2, "0");
                    const s = String(secs % 60).padStart(2, "0");
                    timerEl.textContent = m + ":" + s;
                }, 1000);
            }

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
        </script>
    </body>
</html>
