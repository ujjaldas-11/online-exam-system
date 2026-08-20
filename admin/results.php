<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

$selected_dept = $_GET['department'] ?? 'All';

$deptStmt = $pdo->query("SELECT DISTINCT department FROM subjects ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

$params = [];
$sql = "SELECT e.id, e.title, e.total_marks, s.department, s.semester,
        (SELECT COUNT(*) FROM exam_attempts
        WHERE exam_id = e.id AND status = 'completed') AS total_attempts
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id";

if ($selected_dept !== 'All') {
    $sql .= " WHERE s.department = :dept";
    $params[':dept'] = $selected_dept;
}

$sql .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Dashboard • Examify</title>

    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --card: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 60px;
        }

        h1 {
            font-size: 1.6rem;
            margin-bottom: 4px;
        }

        .subtitle {
            color: var(--gray);
            margin-bottom: 28px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }

        /* Department filters */
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .filter {
            padding: 7px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--gray);
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
        }

        .filter:hover {
            border-color: #bfdbfe;
            color: var(--primary);
        }

        .filter.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Table */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .94rem;
        }

        th,
        td {
            padding: 13px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f1f5f9;
            color: #475569;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        tbody tr:hover td {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: var(--primary);
            font-size: .8rem;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 7px 13px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-size: .85rem;
            font-weight: 600;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: var(--gray);
        }

        @media (max-width: 640px) {
            .container {
                padding: 24px 16px 40px;
            }

            .card {
                padding: 18px;
            }

            h1 {
                font-size: 1.45rem;
            }

            .table-wrap {
                margin: 0 -2px;
            }

            th,
            td {
                padding: 11px 10px;
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>

<?php include '../components/navbar.php'; ?>

<div class="container">

    <h1>Results Dashboard</h1>
    <p class="subtitle">
        View student scores and exam performance
    </p>

    <div class="card">

        <div class="filters">
            <a href="results.php" class="filter <?= $selected_dept === 'All' ? 'active' : '' ?>">
                All Departments
            </a>

            <?php foreach ($departments as $dept): ?>
                <a href="results.php?department=<?= urlencode($dept) ?>" class="filter <?= $selected_dept === $dept ? 'active' : '' ?>">
                    <?= htmlspecialchars($dept) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
            $search_placeholder = "Search exams, departments, or marks...";
            include '../components/searchbar.php';
        ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Exam</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Marks</th>
                        <th>Submissions</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($exams)): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No exams found for
                            <strong><?= htmlspecialchars($selected_dept) ?></strong>.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($exams as $exam): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($exam['title']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($exam['department']) ?>
                            </td>

                            <td>
                                Semester <?= htmlspecialchars($exam['semester']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($exam['total_marks']) ?> marks
                            </td>

                            <td>
                                <span class="badge">
                                    <?= htmlspecialchars($exam['total_attempts']) ?>
                                    students
                                </span>
                            </td>

                            <td>
                                <a
                                    href="view-results.php?exam_id=<?= $exam['id'] ?>"
                                    class="btn"
                                >
                                    View Results
                                </a>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>
            </table>
        </div>

    </div>
</div>

</body>
</html>
