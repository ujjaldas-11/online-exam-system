<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');



$message = '';
$message_type = '';

// ===================== CREATE EXAM =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title           = trim(strip_tags($_POST['title'] ?? ''));
    $subject_id      = (int)($_POST['subject_id'] ?? 0);
    $duration        = (int)($_POST['duration_minutes'] ?? 0);
    $total_marks     = (int)($_POST['total_marks'] ?? 0);
    $total_questions = (int)($_POST['total_questions_to_ask'] ?? 0);

    if (empty($title) || $subject_id <= 0 || $duration <= 0 || $total_marks <= 0 || $total_questions <= 0) {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    } else {
        // Check available questions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $available = (int)$stmt->fetchColumn();

        if ($available < $total_questions) {
            $message = "This subject only has $available questions. You cannot ask for $total_questions.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO exams 
                    (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status) 
                    VALUES (?, ?, ?, ?, ?, 'inactive')
                ");
                $stmt->execute([$title, $subject_id, $duration, $total_marks, $total_questions]);

                $message = "Exam created successfully! It is currently inactive.";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// ===================== START EXAM =====================
if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    $exam_id = (int)$_GET['id'];

    $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $status = $stmt->fetchColumn();

    if (!$status) {
        $message = "Exam not found.";
        $message_type = 'error';
    } elseif ($status === 'active') {
        $message = "Exam is already active.";
        $message_type = 'error';
    } else {
        $pdo->prepare("UPDATE exams SET status = 'active', start_time = NOW() WHERE id = ?")
            ->execute([$exam_id]);

        $message = "Exam has been started successfully. Students can now join.";
        $message_type = 'success';
    }
}

// ===================== AUTO END EXAMS =====================
try {
    $pdo->exec("
        UPDATE exams 
        SET status = 'ended' 
        WHERE status = 'active' 
          AND start_time IS NOT NULL 
          AND DATE_ADD(start_time, INTERVAL duration_minutes MINUTE) <= NOW()
    ");
} catch (PDOException $e) {
    // silent
}

// ===================== FETCH DATA =====================
$exams = $pdo->query("
    SELECT e.*, s.name AS subject_name, s.department, s.semester 
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    ORDER BY e.id DESC
")->fetchAll();

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
            --warning: #d97706;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert.success { background: #dcfce7; color: var(--success); }
        .alert.error { background: #fee2e2; color: var(--error); }

        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 { font-size: 1.15rem; margin-bottom: 16px; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { margin-bottom: 4px; }
        .form-group.full { grid-column: 1 / -1; }

        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: #334155;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            margin-top: 12px;
        }
        .btn:hover { background: #1d4ed8; }

        .btn-start {
            display: inline-block;
            background: #16a34a;
            color: white !important;
            padding: 6px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            white-space: nowrap;
        }
        .btn-start:hover { background: #15803d; }

        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge.active   { background: #dcfce7; color: var(--success); }
        .badge.inactive { background: #fee2e2; color: var(--error); }
        .badge.ended    { background: #e2e8f0; color: #475569; }

        @media (max-width: 700px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'admin-navbar.php'; ?>

<div class="container">
    <h1>Manage Exams</h1>
    <p class="subtitle">Create exams and control when students can start</p>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Create Exam -->
    <div class="card">
        <h2>Create New Exam</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Exam Title</label>
                    <input type="text" name="title" required placeholder="e.g. Mid-Term Operating Systems">
                </div>

                <div class="form-group full">
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>">
                                <?= htmlspecialchars($sub['name']) ?> 
                                (<?= htmlspecialchars($sub['department']) ?>, Sem <?= $sub['semester'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Questions to Ask</label>
                    <input type="number" name="total_questions_to_ask" min="1" required placeholder="e.g. 10">
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" min="1" required placeholder="e.g. 60">
                </div>

                <div class="form-group">
                    <label>Total Marks</label>
                    <input type="number" name="total_marks" min="1" required placeholder="e.g. 50">
                </div>
            </div>

            <button type="submit" name="create_exam" class="btn">Create Exam</button>
        </form>
    </div>

    <!-- Exam List -->
    <div class="card">
        <h2>Exam Control Center (<?= count($exams) ?>)</h2>
        <?php
                $search_placeholder = "Search students, roll number, or department...";
                include '../components/searchbar.php';
        ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>Questions</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Start Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:var(--gray);">No exams created yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php $status = $exam['status']; ?>
                            <tr>
                                <td><?= $exam['id'] ?></td>
                                <td><?= htmlspecialchars($exam['title']) ?></td>
                                <td>
                                    <?= htmlspecialchars($exam['subject_name']) ?><br>
                                    <small style="color:var(--gray)">
                                        <?= htmlspecialchars($exam['department']) ?>, Sem <?= $exam['semester'] ?>
                                    </small>
                                </td>
                                <td><?= $exam['total_questions_to_ask'] ?></td>
                                <td><?= $exam['duration_minutes'] ?> min</td>
                                <td>
                                    <span class="badge <?= htmlspecialchars($status) ?>">
                                        <?= strtoupper(htmlspecialchars($status)) ?>
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
                                    <?php if ($status === 'inactive'): ?>
                                        <a href="?action=start&id=<?= $exam['id'] ?>" 
                                           class="btn-start"
                                           onclick="return confirm('Start this exam now? Students will be able to join immediately.')">
                                            ▶ Start Exam
                                        </a>
                                    <?php elseif ($status === 'active'): ?>
                                        <span style="color:#16a34a; font-weight:600; font-size:0.85rem;">● Running</span>
                                    <?php elseif ($status === 'ended'): ?>
                                        <span style="color:#64748b; font-size:0.85rem;">Ended</span>
                                    <?php else: ?>
                                        <span style="color:#64748b; font-size:0.85rem;">—</span>
                                    <?php endif; ?>
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