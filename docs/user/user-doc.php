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
        <style>
            /* ===== Modern CSS Variables & Theming (Examify Snapshot Theme) ===== */
            :root {
                /* Light Mode / Base */
                --bg-main: #f4f7fb;       /* Soft light background */
                --bg-panel: #ffffff;      /* White panels */
                --text-main: #131b2c;     /* Deep navy text (from screenshot) */
                --text-muted: #4b5563;
                
                --primary: #1a46b9;       /* Vibrant royal blue (from screenshot button) */
                --primary-hover: #163a99;
                
                --accent-green: #10b981; 
                --accent-yellow: #f59e0b; 
                
                --border: #e2e8f0;
                --border-strong: #cbd5e1;
                
                /* Dark Navy Topbar to match screenshot header */
                --topbar-bg: rgba(19, 27, 44, 0.95);
                --topbar-text: #f8fafc;
                --topbar-border: #2c364c;
                --topbar-input-bg: rgba(255, 255, 255, 0.08);

                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                --radius: 12px;
                --transition: all 0.2s ease-in-out;
            }

            /* Dark Mode Variables */
            [data-theme="dark"] {
                --bg-main: #0b1121;       /* Very dark navy space */
                --bg-panel: #131b2c;      /* Navy cards */
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                
                --primary: #3b82f6;       /* Brighter blue for dark mode visibility */
                --primary-hover: #60a5fa;
                
                --border: #2a344a;
                --border-strong: #3b4763;
                
                --topbar-bg: rgba(11, 17, 33, 0.95);
                --topbar-text: #f8fafc;
                --topbar-border: #2a344a;
                --topbar-input-bg: rgba(255, 255, 255, 0.05);
                
                --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.5);
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            html {
                scroll-behavior: smooth;
            }

            body {
                background: var(--bg-main);
                color: var(--text-main);
                font-family: "Inter", system-ui, -apple-system, sans-serif;
                font-size: 16px;
                line-height: 1.6;
                transition: background-color 0.3s, color 0.3s;
            }

            ::selection {
                background: var(--primary);
                color: #fff;
            }

            h1, h2, h3, h4 {
                color: var(--text-main);
                font-weight: 800; /* Bolder headings to match image typography */
                line-height: 1.2;
                margin-bottom: 0.5em;
            }

            a {
                color: var(--primary);
                text-decoration: none;
                transition: var(--transition);
            }
            
            a:hover {
                color: var(--primary-hover);
            }

            code, .mono {
                font-family: "Fira Code", ui-monospace, SFMono-Regular, monospace;
                font-size: 0.9em;
            }

            /* ===== Dark Navy Glassmorphism Topbar ===== */
            .topbar {
                position: sticky;
                top: 0;
                z-index: 50;
                background: var(--topbar-bg);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-bottom: 1px solid var(--topbar-border);
                color: var(--topbar-text);
            }

            .topbar-inner {
                max-width: 1300px;
                margin: 0 auto;
                display: flex;
                align-items: center;
                gap: 20px;
                padding: 14px 32px;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 12px;
                font-weight: 800;
                font-size: 1.3rem;
                color: var(--topbar-text);
                letter-spacing: -0.02em;
            }
            
            .brand:hover {
                color: #fff;
            }

            .brand .logo-icon {
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, #1d4ed8, #60a5fa);
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .badge-doc-type {
                font-size: 0.72rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                background: rgba(255, 255, 255, 0.15);
                color: #fff;
                padding: 4px 12px;
                border-radius: 20px;
            }

            /* ===== Search Box (Adapted for dark navbar) ===== */
            .search-wrap {
                position: relative;
                margin-left: auto;
            }

            .search-wrap input {
                background: var(--topbar-input-bg);
                border: 1px solid rgba(255, 255, 255, 0.15);
                color: #fff;
                padding: 10px 36px 10px 40px;
                border-radius: 30px;
                font-family: inherit;
                font-size: 0.9rem;
                width: 220px;
                transition: var(--transition);
            }

            .search-wrap input::placeholder {
                color: rgba(255, 255, 255, 0.6);
            }

            .search-wrap input:focus {
                outline: none;
                width: 280px;
                background: rgba(255, 255, 255, 0.15);
                border-color: #60a5fa;
                box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
            }

            .search-wrap svg {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                color: rgba(255, 255, 255, 0.6);
                pointer-events: none;
            }

            .search-clear {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: transparent;
                border: none;
                color: rgba(255, 255, 255, 0.6);
                font-size: 1.25rem;
                line-height: 1;
                cursor: pointer;
                padding: 2px 6px;
                border-radius: 50%;
                transition: var(--transition);
            }

            .search-clear:hover {
                color: #fff;
                background: rgba(255, 255, 255, 0.2);
            }

            .doc-section.all-filtered-out {
                display: none !important;
            }

            .toc-link.dimmed {
                opacity: 0.25;
            }

            /* ===== Buttons (Navbar variations) ===== */
            .topbar-links {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 18px;
                border-radius: 8px; /* Squared off a bit like the screenshot */
                font-size: 0.9rem;
                font-weight: 700;
                cursor: pointer;
                border: none;
                transition: var(--transition);
            }

            .btn-outline {
                background: transparent;
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: #fff;
            }

            .btn-outline:hover {
                background: rgba(255, 255, 255, 0.1);
                border-color: #fff;
            }

            .btn-primary {
                background: #1a46b9;
                color: #ffffff !important;
                box-shadow: 0 2px 10px rgba(26, 70, 185, 0.4);
            }

            .btn-primary:hover {
                background: #143694;
                transform: translateY(-1px);
            }

            .btn-icon {
                padding: 8px;
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid transparent;
                color: #fff;
                display: flex;
                cursor: pointer;
                transition: var(--transition);
            }
            .btn-icon:hover {
                background: rgba(255, 255, 255, 0.15);
            }

            /* ===== Layout & Sidebar ===== */
            .shell {
                max-width: 1300px;
                margin: 0 auto;
                display: grid;
                grid-template-columns: 280px 1fr;
                gap: 40px;
            }

            .sidebar {
                position: sticky;
                top: 75px;
                height: calc(100vh - 75px);
                overflow-y: auto;
                padding: 40px 16px 40px 32px;
                scrollbar-width: thin;
            }

            .sidebar::-webkit-scrollbar { width: 6px; }
            .sidebar::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 4px; }

            .toc-group { margin-bottom: 28px; }
            
            .toc-role-label {
                font-size: 0.75rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--text-muted);
                margin: 0 0 12px 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .toc-role-label .chip {
                width: 8px;
                height: 8px;
                border-radius: 2px;
                display: inline-block;
            }

            .toc-group ul {
                list-style: none;
            }

            .toc-link {
                display: block;
                padding: 8px 12px;
                border-radius: 6px;
                color: var(--text-muted);
                font-size: 0.95rem;
                margin-bottom: 4px;
                border-left: 2px solid transparent;
            }

            .toc-link.top {
                font-weight: 700;
                color: var(--text-main);
            }

            .toc-group ul ul {
                margin-left: 12px;
                border-left: 1px solid var(--border);
                padding-left: 8px;
            }

            .toc-link:hover {
                background: var(--bg-panel);
                color: var(--text-main);
                box-shadow: var(--shadow-sm);
            }

            .toc-link.active {
                background: rgba(26, 70, 185, 0.08);
                color: var(--primary);
                font-weight: 700;
                border-left: 2px solid var(--primary);
            }

            /* ===== Main Content Area ===== */
            main {
                padding: 0 32px 100px 0;
                min-width: 0;
            }

            /* ===== Hero Section ===== */
            .hero {
                padding: 60px 0 50px 0;
                border-bottom: 1px solid var(--border);
                display: grid;
                grid-template-columns: 1.2fr 1fr;
                gap: 50px;
                align-items: center;
            }

            .hero-eyebrow {
                font-size: 0.85rem;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: var(--primary);
                margin-bottom: 16px;
            }

            .hero h1 {
                font-size: 3.5rem;
                letter-spacing: -0.04em;
                margin-bottom: 20px;
                color: var(--text-main);
            }

            .hero p.lede {
                font-size: 1.15rem;
                color: var(--text-muted);
                max-width: 90%;
            }

            .hero-stats {
                display: flex;
                gap: 40px;
                margin-top: 32px;
            }

            .hero-stat .num {
                font-size: 2.5rem;
                font-weight: 800;
                color: var(--text-main);
                line-height: 1;
            }

            .hero-stat .label {
                font-size: 0.8rem;
                color: var(--text-muted);
                font-weight: 700;
                text-transform: uppercase;
                margin-top: 8px;
            }

            /* ===== Interactive Palette Widget ===== */
            .palette-card {
                background: var(--bg-panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                box-shadow: var(--shadow-md);
                padding: 24px;
            }

            .palette-card .pc-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .palette-card .pc-title {
                font-weight: 700;
                font-size: 0.9rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .palette-card .pc-timer {
                font-family: "Fira Code", monospace;
                font-weight: 700;
                font-size: 1rem;
                background: rgba(245, 158, 11, 0.15);
                color: var(--accent-yellow);
                padding: 6px 12px;
                border-radius: 6px;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }

            .palette-grid {
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 8px;
                margin-bottom: 20px;
            }

            .palette-grid .cell {
                aspect-ratio: 1;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.85rem;
                font-weight: 800;
                color: #fff;
                cursor: pointer;
                transition: var(--transition);
                box-shadow: inset 0 -2px 0 rgba(0,0,0,0.15);
            }

            .palette-grid .cell:hover {
                transform: translateY(-2px);
                filter: brightness(1.1);
            }

            .cell.answered { background: var(--accent-green); }
            .cell.review { background: var(--accent-yellow); color: #fff; }
            .cell.unanswered { background: var(--border-strong); color: var(--text-main); }
            .cell.current {
                outline: 3px solid var(--primary);
                outline-offset: 2px;
            }

            .palette-legend {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
                font-size: 0.85rem;
                color: var(--text-muted);
                font-weight: 600;
            }

            .palette-legend span {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .palette-legend i {
                width: 14px;
                height: 14px;
                border-radius: 4px;
                display: inline-block;
            }

            /* ===== Content Sections & Accordions ===== */
            section.doc-section {
                padding-top: 60px;
                scroll-margin-top: 90px;
            }

            .section-kicker {
                font-size: 0.8rem;
                font-weight: 800;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.1em;
                margin-bottom: 8px;
            }

            section.doc-section h2 {
                font-size: 2.2rem;
                padding-bottom: 16px;
                border-bottom: 2px solid var(--border);
                margin-bottom: 24px;
            }

            .subsection {
                margin: 0 0 16px 0;
                background: var(--bg-panel);
                border: 1px solid var(--border);
                border-radius: var(--radius);
                box-shadow: var(--shadow-sm);
                overflow: hidden;
                scroll-margin-top: 90px;
                transition: var(--transition);
            }

            .subsection:hover {
                border-color: var(--border-strong);
            }

            .subsection.filtered-out {
                display: none;
            }

            .subsection > summary {
                list-style: none;
                cursor: pointer;
                padding: 20px 24px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-weight: 700;
                font-size: 1.1rem;
                color: var(--text-main);
                background: var(--bg-panel);
            }

            .subsection > summary::-webkit-details-marker { display: none; }
            
            .subsection > summary .arrow {
                color: var(--text-muted);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
            }

            .subsection[open] > summary .arrow {
                transform: rotate(180deg);
                color: var(--primary);
            }

            .subsection .body {
                padding: 0 24px 24px 24px;
                color: var(--text-muted);
                border-top: 1px solid var(--border);
                margin-top: 4px;
                padding-top: 20px;
            }

            .subsection .body p { margin-bottom: 16px; }
            .subsection .body ol, .subsection .body ul {
                padding-left: 24px;
                margin-bottom: 16px;
            }
            .subsection .body li { margin-bottom: 8px; }
            .subsection .body strong { color: var(--text-main); }

            /* ===== Tables & Admonitions ===== */
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 16px 0;
                background: var(--bg-main);
                border-radius: 8px;
                overflow: hidden;
            }

            th, td {
                text-align: left;
                padding: 14px 16px;
                border-bottom: 1px solid var(--border);
            }

            th {
                font-size: 0.85rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
                background: var(--bg-panel);
                font-weight: 700;
            }

            .admonition {
                border-left: 4px solid var(--primary);
                background: rgba(26, 70, 185, 0.05);
                padding: 16px 20px;
                border-radius: 0 8px 8px 0;
                margin: 20px 0;
            }

            .admonition.warning {
                border-left-color: #ef4444; 
                background: rgba(239, 68, 68, 0.05);
            }

            .admonition .adm-label {
                font-size: 0.8rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                display: block;
                margin-bottom: 6px;
            }

            .admonition:not(.warning) .adm-label { color: var(--primary); }
            .admonition.warning .adm-label { color: #ef4444; }

            .kbd {
                font-family: inherit;
                background: var(--bg-main);
                border: 1px solid var(--border-strong);
                border-bottom-width: 3px;
                padding: 2px 8px;
                border-radius: 6px;
                font-size: 0.85rem;
                font-weight: 700;
                color: var(--text-main);
            }

            /* ===== Callouts (Styled like image cards) ===== */
            .role-pill {
                display: inline-block;
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                padding: 4px 10px;
                border-radius: 20px;
                margin-left: 12px;
                font-weight: 800;
            }

            .role-pill.student {
                background: rgba(16, 185, 129, 0.15);
                color: var(--accent-green);
            }

            .admin-callout {
                /* Styled like the dark feature cards in the screenshot */
                background: #131b2c;
                border: 1px solid #2a344a;
                border-radius: var(--radius);
                padding: 35px;
                margin-top: 60px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                box-shadow: var(--shadow-md);
            }

            .admin-callout-content h3 { color: #f8fafc; margin-bottom: 8px; }
            .admin-callout-content p { margin: 0; color: #94a3b8; font-size: 1.05rem; }

            footer {
                /* Dark footer to match screenshot */
                background: #0b1121;
                border-top: 1px solid #2a344a;
                padding: 10px;
                text-align: center;
                color: #b8d3f9;
                font-size: 0.9rem;
                margin-top: 60px;
            }

            .no-results {
                display: none;
                padding: 60px 20px;
                text-align: center;
                color: var(--text-muted);
                background: var(--bg-panel);
                border-radius: var(--radius);
                border: 1px dashed var(--border-strong);
                margin-top: 20px;
            }

            /* Floating Scroll to Top Button */
            #scrollTopBtn {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: 12px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
                cursor: pointer;
                box-shadow: var(--shadow-md);
                opacity: 0;
                transform: translateY(20px);
                transition: var(--transition);
                pointer-events: none;
                z-index: 100;
            }
            #scrollTopBtn.visible {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }
            #scrollTopBtn:hover {
                background: var(--primary-hover);
                transform: translateY(-3px);
            }

            @media (max-width: 960px) {
                .shell { grid-template-columns: 1fr; }
                .sidebar { display: none; }
                .hero { grid-template-columns: 1fr; gap: 30px; }
                main { padding: 0 20px; }
                .topbar-inner { flex-wrap: wrap; padding: 14px 20px; }
                .search-wrap { order: 3; width: 100%; margin-top: 12px; }
                .search-wrap input { width: 100%; }
                .admin-callout { flex-direction: column; align-items: flex-start; }
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <div class="topbar-inner">
                <a href="../../index.php" class="brand">
                    <div class="logo-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    Examify Docs
                </a>
                <span class="badge-doc-type">Student Guide</span>
                
                <div class="search-wrap">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <input id="searchInput" type="text" placeholder="Search topics, keywords..." autocomplete="off" />
                    <button type="button" id="searchClear" class="search-clear" title="Clear search" style="display: none;">&times;</button>
                </div>

                <div class="topbar-links">
                    <!-- Dark Mode Toggle -->
                    <button class="btn-icon" id="themeToggle" title="Toggle Theme">
                        <svg id="moonIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                        <svg id="sunIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                    </button>
                    <a href="../../index.php" class="btn btn-outline">Portal Home</a>
                    <a href="admin-doc.php" class="btn btn-primary" title="Restricted to Instructors">Admin Docs &rarr;</a>
                </div>
            </div>
        </div>

        <div class="shell">
            <nav class="sidebar" id="sidebar">
                <div class="toc-group">
                    <div class="toc-role-label">
                        <span class="chip" style="background: var(--border-strong)"></span>Overview
                    </div>
                    <ul>
                        <li><a class="toc-link top" href="#sec-1">1. User Roles</a></li>
                    </ul>
                </div>

                <div class="toc-group">
                    <div class="toc-role-label">
                        <span class="chip" style="background: var(--primary)"></span>Student Portal
                    </div>
                    <ul>
                        <li>
                            <a class="toc-link top" href="#sec-2">2. Portal Guide</a>
                            <ul>
                                <li><a class="toc-link" href="#sec-2-1">2.1 Registration</a></li>
                                <li><a class="toc-link" href="#sec-2-2">2.2 Password Visibility</a></li>
                                <li><a class="toc-link" href="#sec-2-3">2.3 Student Login</a></li>
                                <li><a class="toc-link" href="#sec-2-4">2.4 Hardware Rules</a></li>
                                <li><a class="toc-link" href="#sec-2-5">2.5 Dashboard</a></li>
                                <li><a class="toc-link" href="#sec-2-6">2.6 Classroom PIN</a></li>
                                <li><a class="toc-link" href="#sec-2-7">2.7 Taking an Exam</a></li>
                                <li><a class="toc-link" href="#sec-2-8">2.8 Anti-Cheat Rules</a></li>
                                <li><a class="toc-link" href="#sec-2-9">2.9 Submission</a></li>
                                <li><a class="toc-link" href="#sec-2-10">2.10 Results</a></li>
                                <li><a class="toc-link" href="#sec-2-11">2.11 Student Profile</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <main>
                <div class="hero">
                    <div>
                        <div class="hero-eyebrow">Student & Candidate Handbook</div>
                        <h1>Master the Examify Platform</h1>
                        <p class="lede">
                            A secure, modern online examination platform designed for computer laboratories. 
                            Learn how to register, take assessments securely, and review your performance.
                        </p>
                        <div class="hero-stats">
                            <div class="hero-stat">
                                <div class="num">11</div>
                                <div class="label">Workflows</div>
                            </div>
                            <div class="hero-stat">
                                <div class="num">3</div>
                                <div class="label">Strike Limit</div>
                            </div>
                        </div>
                    </div>

                    <div class="palette-card">
                        <div class="pc-head">
                            <span class="pc-title">Live Palette Demo</span>
                            <span class="pc-timer" id="demoTimer">29:58</span>
                        </div>
                        <div class="palette-grid" id="paletteGrid"></div>
                        <div class="palette-legend">
                            <span><i style="background: var(--accent-green)"></i>Answered</span>
                            <span><i style="background: var(--accent-yellow)"></i>Review</span>
                            <span><i style="background: var(--border-strong)"></i>Pending</span>
                        </div>
                    </div>
                </div>

                <!-- SECTION 1 -->
                <section class="doc-section" id="sec-1">
                    <div class="section-kicker">Getting oriented</div>
                    <h2>1. User Roles</h2>
                    <details class="subsection" open id="sec-1-1">
                        <summary>
                            System User Personas
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
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
                            2.1 Student Account Registration
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>Follow these steps to register a new student account:</p>
                            <ol>
                                <li>Navigate to the application address and click <strong>Student Portal</strong>.</li>
                                <li>Click the <strong>Register here</strong> link.</li>
                                <li>Enter your Full Name and College Email.</li>
                                <li>Type your student ID in the <strong>Roll Number</strong> field (auto-capitalized).</li>
                                <li>Select your academic department (e.g., BCA, BBA) and semester.</li>
                                <li>Create a secure password (minimum 6 characters).</li>
                                <li>Click <strong>Register</strong>.</li>
                            </ol>
                            <p>
                                Your account enters a <code>pending</code> status upon creation. Your department instructor must approve it before you can participate in live examinations.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-2">
                        <summary>
                            2.2 Universal Password Visibility Toggle
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>
                                Every password input field across the student portal includes an interactive eye toggle button:
                            </p>
                            <ul>
                                <li>Click the <strong>Eye Icon</strong> to unmask password characters and verify spelling.</li>
                                <li>Click the <strong>Eye Icon</strong> again to re-mask password characters for confidentiality.</li>
                            </ul>
                            <div class="admonition">
                                <span class="adm-label">Security Reminder</span>
                                Always mask your password before beginning an examination if other students sit near your computer terminal.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-3">
                        <summary>
                            2.3 Student Login and Single-Device Session Rules
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
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
                                Examify allows only <strong>one active session per student</strong>. If you log into your account on a second device, your previous session terminates immediately to prevent unauthorized sharing and maintain exam integrity.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-4">
                        <summary>
                            2.4 Hardware Requirements & Touchscreen Gating
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>Review these mandatory hardware and device gating policies before exam sessions:</p>
                            <ul>
                                <li><strong>Desktop or Laptop Required:</strong> You must take exams on a desktop or laptop computer. The exam room actively blocks mobile phones and tablets.</li>
                                <li><strong>Lockout Screen:</strong> Attempting to access an active examination on a mobile phone or tablet displays a device lockout screen instructing you to switch to a computer.</li>
                                <li><strong>Touchscreen Suppression:</strong> If you use a touchscreen laptop, the examination room suppresses touch taps to prevent cheating gestures. You must interact using your touchpad or an external mouse.</li>
                            </ul>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-5">
                        <summary>
                            2.5 Student Dashboard & Live Exam Discovery
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
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
                            2.6 Unlocking an Exam with a Classroom PIN
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>Instructors frequently assign a 4-digit PIN to secure test sessions:</p>
                            <ol>
                                <li>Click <strong>Start Exam</strong> on the active test card in your dashboard.</li>
                                <li>If prompted, type the 4-digit whiteboard PIN provided by your instructor or proctor.</li>
                                <li>Click <strong>Unlock Exam</strong> to initialize your secure examination session.</li>
                            </ol>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-7">
                        <summary>
                            2.7 Taking an Examination (Interface Guide)
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click <strong>Enter Fullscreen & Begin</strong> to start the exam timer.</li>
                                <li>Read the question text and select your answer option (A, B, C, or D).</li>
                                <li>The system saves your selected option automatically in the background.</li>
                                <li>Click <strong>Next</strong> or <strong>Previous</strong> to navigate questions.</li>
                                <li>Click <strong>Mark for Review</strong> to flag questions for double-checking before final submission.</li>
                            </ol>
                            <h4>Question Palette Navigation</h4>
                            <p>Use the Question Palette to track your test progress:</p>
                            <table>
                                <tr><th>Color</th><th>Meaning</th></tr>
                                <tr><td><strong style="color: var(--accent-green)">Green</strong></td><td>Answer successfully saved</td></tr>
                                <tr><td><strong style="color: var(--accent-yellow)">Yellow</strong></td><td>Marked for review (double-check later)</td></tr>
                                <tr><td><strong style="color: var(--text-muted)">Gray</strong></td><td>Not answered yet</td></tr>
                            </table>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-8">
                        <summary>
                            2.8 Anti-Cheat Security Rules & Cheating Infractions
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>The system actively monitors examination room integrity. Avoid the following prohibited actions:</p>
                            <ul>
                                <li>Exiting full-screen mode or minimizing the exam window.</li>
                                <li>Switching browser tabs or launching other desktop applications.</li>
                                <li>Pressing <span class="kbd">F12</span>, <span class="kbd">Ctrl+Shift+I</span>, or opening developer tools.</li>
                            </ul>
                            <div class="admonition warning">
                                <span class="adm-label">Three-Strike Rule</span>
                                When an infraction occurs, the exam pauses and records an audit log. After <strong>3 violations</strong>, the system instantly terminates your session and submits your current answers.
                            </div>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-9">
                        <summary>
                            2.9 Submitting Answers (In-DOM Confirmation Modal)
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Check your question palette to ensure all desired questions are answered.</li>
                                <li>Click the <strong>Submit Exam</strong> button in the bottom navigation bar.</li>
                                <li>
                                    A custom in-DOM confirmation modal dialog opens on screen displaying live summary metrics:
                                    <ul>
                                        <li><strong>Answered Questions:</strong> Count of completed and saved items.</li>
                                        <li><strong>Marked for Review:</strong> Items flagged for double-checking.</li>
                                        <li><strong>Unanswered Questions:</strong> Items left blank.</li>
                                    </ul>
                                </li>
                                <li>Click <strong>Confirm & Submit</strong> to finalize your examination.</li>
                            </ol>
                            <p>
                                The anti-cheat monitoring automatically stops prior to form submission, preventing false window blur infractions during page transition.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-10">
                        <summary>
                            2.10 Reviewing Results & Downloading PDF Scorecards
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <p>
                                When exam results are published by your instructor, the results page displays your complete performance metrics:
                            </p>
                            <ul>
                                <li>Total marks achieved and maximum possible marks.</li>
                                <li>Percentage score and qualification status (<span class="role-pill student" style="background: rgba(16, 185, 129, 0.2); color: var(--accent-green)">PASS</span> or <span class="role-pill" style="background: rgba(239, 68, 68, 0.2); color: #ef4444">FAIL</span>).</li>
                                <li>Itemized breakdown of correct, incorrect, and unanswered questions.</li>
                            </ul>
                            <p>
                                Click <strong>Download Scorecard PDF</strong> to generate an official institutional grade sheet powered by our local PDF engine.
                            </p>
                        </div>
                    </details>

                    <details class="subsection" id="sec-2-11">
                        <summary>
                            2.11 Student Profile & Academic Detail Update Requests
                            <svg class="arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </summary>
                        <div class="body">
                            <ol>
                                <li>Click your student profile in the navigation header.</li>
                                <li>Review your enrolled department, semester, roll number, and test history.</li>
                                <li>If your academic details need correction, click <strong>Request Profile Update</strong>.</li>
                                <li>Submit your revised details for faculty verification.</li>
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
                        <p>Access the Instructor Portal Guide to manage subjects, question banks, and live proctoring.</p>
                    </div>
                    <a href="admin-doc.php" class="btn btn-primary">Open Admin Docs &rarr;</a>
                </div>

                <div class="no-results" id="noResults">
                    <h3>No matching sections found.</h3>
                    <p>No results found for <strong id="searchQueryDisplay"></strong>. Try searching for different keywords or press <span class="kbd">Esc</span> to reset.</p>
                </div>
            </main>
        </div>

        <button id="scrollTopBtn" title="Go to top">↑</button>

        <footer>
            &copy; <?php echo date("Y"); ?> Examify Educational Systems. All rights reserved.
        </footer>

        <script>
            // ===== Theme Toggler =====
            const themeToggle = document.getElementById('themeToggle');
            const moonIcon = document.getElementById('moonIcon');
            const sunIcon = document.getElementById('sunIcon');

            function getSavedTheme() {
                try {
                    return localStorage.getItem('theme');
                } catch (e) {
                    return null;
                }
            }

            function setSavedTheme(theme) {
                try {
                    localStorage.setItem('theme', theme);
                } catch (e) {}
            }

            function updateThemeUI(theme) {
                if (theme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    if (moonIcon) moonIcon.style.display = 'none';
                    if (sunIcon) sunIcon.style.display = 'block';
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    if (moonIcon) moonIcon.style.display = 'block';
                    if (sunIcon) sunIcon.style.display = 'none';
                }
            }

            // Check saved theme or system preference
            const savedTheme = getSavedTheme();
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initialTheme = savedTheme || (prefersDark ? 'dark' : 'light');
            updateThemeUI(initialTheme);

            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    const newTheme = isDark ? 'light' : 'dark';
                    updateThemeUI(newTheme);
                    setSavedTheme(newTheme);
                });
            }

            // ===== Decorative Palette Grid =====
            const pattern = [
                "answered", "answered", "review", "unanswered",
                "answered", "unanswered", "review", "answered",
                "unanswered", "answered", "answered", "review",
                "unanswered", "unanswered", "answered", "review"
            ];
            const grid = document.getElementById("paletteGrid");
            if (grid) {
                pattern.forEach((status, i) => {
                    const cell = document.createElement("div");
                    cell.className = `cell ${status} ${i === 9 ? "current" : ""}`;
                    cell.textContent = i + 1;
                    grid.appendChild(cell);
                });
            }

            // ===== Decorative Timer =====
            let secs = 29 * 60 + 58;
            const timerEl = document.getElementById("demoTimer");
            if (timerEl) {
                setInterval(() => {
                    secs = secs > 0 ? secs - 1 : 29 * 60 + 58;
                    const m = String(Math.floor(secs / 60)).padStart(2, "0");
                    const s = String(secs % 60).padStart(2, "0");
                    timerEl.textContent = `${m}:${s}`;
                }, 1000);
            }

            // ===== Robust Instant Search & Filter =====
            const searchInput = document.getElementById("searchInput");
            const searchClear = document.getElementById("searchClear");
            const noResults = document.getElementById("noResults");
            const searchQueryDisplay = document.getElementById("searchQueryDisplay");
            const heroSection = document.querySelector(".hero");
            const adminCallout = document.querySelector(".admin-callout");
            const docSections = Array.from(document.querySelectorAll(".doc-section"));
            const allSubsections = Array.from(document.querySelectorAll(".subsection"));
            const tocLinks = Array.from(document.querySelectorAll(".toc-link"));

            // Cache initial default open state of subsections
            const defaultOpenStates = new Map();
            allSubsections.forEach((sec) => {
                defaultOpenStates.set(sec, sec.hasAttribute("open"));
            });

            function performSearch() {
                const rawQuery = searchInput ? searchInput.value : "";
                const query = rawQuery.trim().toLowerCase();
                const terms = query.split(/\s+/).filter(Boolean);
                const isSearching = terms.length > 0;

                // Toggle clear button
                if (searchClear) {
                    searchClear.style.display = isSearching ? "block" : "none";
                }

                // Hide/show hero & callouts during active search
                if (heroSection) heroSection.style.display = isSearching ? "none" : "";
                if (adminCallout) adminCallout.style.display = isSearching ? "none" : "";

                let totalVisible = 0;

                docSections.forEach((section) => {
                    const sectionKicker = section.querySelector(".section-kicker")?.textContent.toLowerCase() || "";
                    const sectionTitle = section.querySelector("h2")?.textContent.toLowerCase() || "";
                    const sectionSubsections = Array.from(section.querySelectorAll(".subsection"));
                    let sectionMatches = 0;

                    sectionSubsections.forEach((sec) => {
                        const secSummary = sec.querySelector("summary")?.textContent.toLowerCase() || "";
                        const secBody = sec.querySelector(".body")?.textContent.toLowerCase() || "";
                        const fullText = `${sectionKicker} ${sectionTitle} ${secSummary} ${secBody}`;

                        // Check if all search terms match
                        const match = !isSearching || terms.every((term) => fullText.includes(term));

                        sec.classList.toggle("filtered-out", !match);

                        if (match) {
                            totalVisible++;
                            sectionMatches++;
                            if (isSearching) {
                                sec.open = true; // Auto-expand when searching
                            } else {
                                sec.open = defaultOpenStates.get(sec) || false; // Restore default state
                            }
                        }
                    });

                    // Hide the entire section container if no subsections inside matched
                    section.classList.toggle("all-filtered-out", isSearching && sectionMatches === 0);
                });

                // Filter / highlight TOC links
                tocLinks.forEach((link) => {
                    const href = link.getAttribute("href");
                    if (!href || href === "#") return;
                    if (!isSearching) {
                        link.classList.remove("dimmed");
                        return;
                    }
                    const targetEl = document.querySelector(href);
                    if (targetEl) {
                        const isHidden = targetEl.classList.contains("filtered-out") || targetEl.classList.contains("all-filtered-out");
                        link.classList.toggle("dimmed", isHidden);
                    }
                });

                if (noResults) {
                    noResults.style.display = isSearching && totalVisible === 0 ? "block" : "none";
                    if (searchQueryDisplay) {
                        searchQueryDisplay.textContent = `"${rawQuery.trim()}"`;
                    }
                }
            }

            if (searchInput) {
                let searchDebounce;
                searchInput.addEventListener("input", () => {
                    clearTimeout(searchDebounce);
                    searchDebounce = setTimeout(performSearch, 80);
                });

                searchInput.addEventListener("keydown", (e) => {
                    if (e.key === "Escape") {
                        searchInput.value = "";
                        performSearch();
                        searchInput.blur();
                    }
                });
            }

            if (searchClear) {
                searchClear.addEventListener("click", () => {
                    searchInput.value = "";
                    performSearch();
                    searchInput.focus();
                });
            }

            // ===== Active TOC Highlighting =====
            const targets = tocLinks
                .map((link) => {
                    const href = link.getAttribute("href");
                    return href && href.startsWith("#") ? document.querySelector(href) : null;
                })
                .filter(Boolean);

            if (typeof IntersectionObserver !== "undefined" && targets.length > 0) {
                const observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                const id = "#" + entry.target.id;
                                const activeLink = document.querySelector(`.toc-link[href="${id}"]`);
                                if (activeLink) {
                                    tocLinks.forEach((l) => l.classList.remove("active"));
                                    activeLink.classList.add("active");
                                }
                            }
                        });
                    },
                    { rootMargin: "-10% 0px -80% 0px", threshold: 0 }
                );
                targets.forEach((target) => observer.observe(target));
            }

            // ===== Scroll to Top Button =====
            const scrollTopBtn = document.getElementById("scrollTopBtn");
            if (scrollTopBtn) {
                window.addEventListener("scroll", () => {
                    if (window.scrollY > 300) {
                        scrollTopBtn.classList.add("visible");
                    } else {
                        scrollTopBtn.classList.remove("visible");
                    }
                });
                scrollTopBtn.addEventListener("click", () => {
                    window.scrollTo({ top: 0, behavior: "smooth" });
                });
            }
        </script>
    </body>
</html>