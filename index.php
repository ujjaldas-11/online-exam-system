<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examify • Online Examination System</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navbar */
        nav {
            background: white;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
            font-weight: 700;
            font-size: 2.00rem;
            color: var(--dark);
            text-decoration: none;
        }
        .logo span { color: var(--primary); }

        /* Hero */
        .hero {
            background: var(--dark);
            color: white;
            text-align: center;
            padding: 90px 20px 100px;
        }
        .hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }
        .hero p {
            font-size: 1.15rem;
            color: #94a3b8;
            max-width: 520px;
            margin: 0 auto 36px;
        }

        .cta {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 13px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-outline {
            background: transparent;
            color: white;
            border: 1.5px solid #475569;
        }
        .btn-outline:hover {
            border-color: white;
            background: rgba(255,255,255,0.05);
        }

        /* Features */
        .features {
            max-width: 1100px;
            margin: 0 auto;
            padding: 70px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px 24px;
            text-align: center;
        }
        .card .icon {
            font-size: 2rem;
            margin-bottom: 14px;
        }
        .card h3 {
            font-size: 1.15rem;
            margin-bottom: 8px;
        }
        .card p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 24px 20px;
            color: var(--gray);
            font-size: 0.9rem;
            border-top: 1px solid var(--border);
        }

        /* Mobile */
        @media (max-width: 640px) {
            .hero { padding: 70px 20px 80px; }
            .hero h1 { font-size: 2.1rem; }
            .hero p { font-size: 1.05rem; }
            .cta { flex-direction: column; align-items: center; }
            .btn { width: 100%; max-width: 280px; text-align: center; }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="index.php" class="logo">Exam<span>ify</span></a>
    </div>
</nav>

<header class="hero">
    <h1>Welcome to Examify</h1>
    <p>A modern and secure platform for conducting online semester examinations.</p>

    <div class="cta">
        <a href="student/login.php" class="btn btn-primary">Student Portal</a>
        <a href="admin/admin-login.php" class="btn btn-outline">Admin Portal</a>
    </div>
</header>

<section class="features">
    <div class="card">
        <div class="icon">⏱️</div>
        <h3>Synchronized Timers</h3>
        <p>Server-side timers ensure every student starts and ends at the exact same time.</p>
    </div>

    <div class="card">
        <div class="icon">🛡️</div>
        <h3>Secure Submissions</h3>
        <p>Transactions and unique constraints prevent lost answers and duplicate attempts.</p>
    </div>

    <div class="card">
        <div class="icon">📊</div>
        <h3>Instant Auto-Grading</h3>
        <p>Objective questions are graded immediately and results are available right after submission.</p>
    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> Examify. All rights reserved.
</footer>

</body>
</html>