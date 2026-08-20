<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_exam'])) {
    $exam_id = (int)$_POST['exam_id'];

    try {
        $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$exam_id]);
        $message = "Exam deleted successfully.";
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = "Cannot delete exam: Students have already submitted answers for it.";
        $message_type = 'error';
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $exam_id = (int)$_GET['id'];
    $action = $_GET['action'];

    $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $status = $stmt->fetchColumn();

    if (!$status) {
        $message = "Exam not found.";
        $message_type = 'error';
    } else {
        if ($action === 'start' && $status === 'inactive') {
            $pdo->prepare("UPDATE exams SET status = 'active', start_time = NOW() WHERE id = ?")
                ->execute([$exam_id]);
            $message = "Exam has been started successfully. Students can now join.";
            $message_type = 'success';
        }
    }
}


$exams = $pdo->query("
    SELECT e.*, s.name AS subject_name, s.department, s.semester
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    ORDER BY e.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Exams • Examify</title>
    <style>
        :root { --primary: #2563eb; --dark: #0f172a; --gray: #64748b; --light: #f8fafc; --border: #e2e8f0; --success: #16a34a; --error: #dc2626; --warning: #d97706; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--light); color: var(--dark); line-height: 1.5; }
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #dcfce7; color: var(--success); }
        .alert.error { background: #fee2e2; color: var(--error); }
        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }

        .action-flex { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        .btn-sm { display: inline-block; padding: 6px 12px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border-radius: 6px; cursor: pointer; border: none; }
        .btn-start { background: #16a34a; color: white; }
        .btn-start:hover { background: #15803d; }
        .btn-delete { background: var(--error); color: white; }
        .btn-delete:hover { background: #b91c1c; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-running { background: #dcfce7; color: var(--success); border: 1px solid #bbf7d0; }
        .badge-notstarted { background: #f1f5f9; color: var(--gray); border: 1px solid var(--border); }
        .badge-ended { background: #fee2e2; color: var(--error); border: 1px solid #fecaca;}

        .btn {
            background-color: var(--primary);
            padding: 6px 10px;
            color: var(--light);
            font-weight: bold;
            border-radius: 8px;
            border:none;
            cursor: pointer;
            font-size: large;
        }
    </style>
</head>
<body>

<?php include '../components/navbar.php'; ?>

<div class="container">
    <h1>Exam Control Center</h1>
    <p class="subtitle">Monitor active exams and manage sessions</p>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 16px;">All Exams (<?= count($exams) ?>)</h2>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <?php
            $search_placeholder = "Search exam title, subject, or status...";
            include '../components/searchbar.php';
            ?>
            <a href="manage-exam.php">
                <button name="create_exam" class="btn"">Create Exam</button>
            </a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Format</th>
                        <th>Status</th>
                        <th>Start Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:var(--gray);">No exams created yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                                // DYNAMIC STATUS CALCULATOR
                                $display_status = 'NOT STARTED';
                                $badge_class = 'badge-notstarted';

                                if ($exam['status'] === 'active') {
                                    $start_timestamp = strtotime($exam['start_time']);
                                    $duration_seconds = $exam['duration_minutes'] * 60;
                                    $end_timestamp = $start_timestamp + $duration_seconds;

                                    if (time() >= $end_timestamp) {
                                        $display_status = 'ENDED';
                                        $badge_class = 'badge-ended';
                                    } else {
                                        $display_status = 'RUNNING';
                                        $badge_class = 'badge-running';
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= $exam['id'] ?></td>
                                <td><strong><?= htmlspecialchars($exam['title']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($exam['subject_name']) ?><br>
                                    <small style="color:var(--gray)">
                                        <?= htmlspecialchars($exam['department']) ?>, Sem <?= $exam['semester'] ?>
                                    </small>
                                </td>
                                <td>
                                    <?= $exam['total_questions_to_ask'] ?> Qs<br>
                                    <small style="color:var(--gray)"><?= $exam['duration_minutes'] ?> mins</small>
                                </td>

                                <td>
                                    <span class="badge <?= $badge_class ?>">
                                        <?php if ($display_status === 'RUNNING') echo '▶ '; ?>
                                        <?= $display_status ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($exam['start_time']): ?>
                                        <?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray)">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-flex">
                                        <!-- Only show Start button if it's NOT STARTED -->
                                        <?php if ($display_status === 'NOT STARTED'): ?>
                                            <a href="?action=start&id=<?= $exam['id'] ?>" class="btn-sm btn-start" onclick="return confirm('Start this exam now? Students will be able to join immediately.')">▶ Start</a>
                                        <?php endif; ?>

                                        <!-- Delete Button -->
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('⚠️ WARNING: Are you sure you want to permanently delete this exam?');">
                                            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                            <button type="submit" name="delete_exam" class="btn-sm btn-delete">🗑️ Delete</button>
                                        </form>
                                    </div>
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
