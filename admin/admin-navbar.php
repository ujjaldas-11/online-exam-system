
<style>
    nav {
            background: var(--dark);
            color: white;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { font-weight: 700; font-size: 1.25rem; }
        .nav-links { display: flex; gap: 6px; }
        .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .nav-links a:hover { background: #1e293b; color: white; }
        .logout { background: #dc2626 !important; color: white !important; }
        .menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }


        @media (max-width: 768px) {
            .menu-btn { display: block; }
            .nav-links {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
                background: var(--dark);
                flex-direction: column;
                padding: 12px;
                gap: 4px;
            }
            .nav-links.show { display: flex; }
            .nav-links a { padding: 12px; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr; }
        }

</style>


<nav class="navbar">

            <nav>
                <div class="nav-inner">
                    <div class="logo">Examify Admin</div>
                    <button class="menu-btn" onclick="document.querySelector('.nav-links').classList.toggle('show')">☰</button>
                    <div class="nav-links">
                        <a href="admin-dashboard.php">Dashboard</a>
                        <a href="manage-subjects.php">Subjects</a>
                        <a href="manage-questions.php">Questions</a>
                        <a href="manage-exam.php">Exams</a>
                        <a href="results.php">Results</a>
                        <a href="admin-logout.php" class="logout">Logout</a>
                    </div> 
                </div>
            </nav>

    </div>
</nav>