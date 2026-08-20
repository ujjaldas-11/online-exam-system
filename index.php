<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Examify • Online Examination System</title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >
</head>

<body>

    <!-- =========================
        NAVIGATION
    ========================= -->

    <nav aria-label="College navigation">

        <div class="nav-bar">

            <a
                href="https://www.bistpurulia.org/"
                class="college-brand"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Visit Bengal Institute of Science and Technology website"
            >

                <img
                    src="assets/images/college_logo.png"
                    alt="Bengal Institute of Science and Technology logo"
                >

                <div class="college-name">
                    <span>B</span>engal
                    <span>I</span>nstitute of
                    <span>S</span>cience and
                    <span>T</span>echnology
                </div>

            </a>

        </div>

    </nav>


    <!-- =========================
        HERO
    ========================= -->

    <header class="hero">

        <div class="hero-content">

            <div class="examify-logo-wrapper">

                <img
                    src="assets/images/examify_logo.png"
                    alt="Examify"
                    class="examify-logo"
                >

            </div>


            <h1>Examify</h1>


            <p class="hero-description">
                A modern and secure platform for conducting
                online semester examinations.
            </p>


            <div class="cta">

                <a
                    href="student/login.php"
                    class="btn btn-primary"
                >
                    Student Portal
                </a>

                <a
                    href="admin/admin-login.php"
                    class="btn btn-outline"
                >
                    Admin Portal
                </a>

            </div>

        </div>

    </header>


    <!-- =========================
        FEATURES
    ========================= -->

    <section
        class="features"
        aria-label="Examify features"
    >

        <div class="card">

            <div class="icon" aria-hidden="true">

                <svg viewBox="0 0 24 24">
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    ></circle>

                    <path d="M12 7v5l3 2"></path>
                </svg>

            </div>

            <h3>Synchronized Timers</h3>

            <p>
                Server-side timers ensure every student starts
                and ends at the exact same time.
            </p>

        </div>


        <div class="card">

            <div class="icon" aria-hidden="true">

                <svg viewBox="0 0 24 24">

                    <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"></path>

                    <path d="M9 12l2 2 4-4"></path>

                </svg>

            </div>

            <h3>Secure Submissions</h3>

            <p>
                Transactions and unique constraints help prevent
                lost answers and duplicate attempts.
            </p>

        </div>


        <div class="card">

            <div class="icon" aria-hidden="true">

                <svg viewBox="0 0 24 24">

                    <path d="M4 19V5"></path>

                    <path d="M4 19h16"></path>

                    <path d="M7 15l3-4 3 2 5-7"></path>

                </svg>

            </div>

            <h3>Instant Auto-Grading</h3>

            <p>
                Objective questions are graded immediately and
                results are available right after submission.
            </p>

        </div>

    </section>


    <!-- =========================
        FOOTER
    ========================= -->

    <footer>
        &copy; <?= date('Y') ?> Examify. All rights reserved.
    </footer>

</body>

</html>
