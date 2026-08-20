<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    $name       = trim(strip_tags($_POST['name'] ?? ''));
    $department = trim(strip_tags($_POST['department'] ?? ''));
    $semester   = (int)($_POST['semester'] ?? 0);

    if (empty($name) || empty($department) || $semester < 1 || $semester > 8) {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES (?, ?, ?)");
            $stmt->execute([$name, $department, $semester]);
            $message = "Subject created successfully!";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-soft: #eff6ff;
            --text: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --card: #ffffff;
            --success: #16a34a;
            --error: #dc2626;
            --shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* Header */
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .subtitle {
            color: var(--text-secondary);
            margin-top: 4px;
            font-size: 0.95rem;
        }

        /* Alert */
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .alert.success {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }
        .alert.error {
            background: #fee2e2;
            color: var(--error);
            border: 1px solid #fecaca;
        }

        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: var(--shadow);
        }
        .card h2 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .form-group.full {
            grid-column: 1 / -1;
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: #334155;
        }
        input, select {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 0.95rem;
            background: white;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 7px 14px;
            font-size: 0.85rem;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.15s;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* Table */
        .table-wrap {
            overflow-x: auto;
            margin-top: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.93rem;
        }
        th, td {
            padding: 13px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background: #fafbfc;
        }

        .empty-state {
            text-align: center;
            color: var(--text-secondary);
            padding: 30px 0;
        }

        @media (max-width: 700px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 24px 16px 40px;
            }
        }
    </style>
</head>
<body>

<?php include '../components/navbar.php'; ?>

<div class="container">

    <div class="page-header">
        <div>
            <h1>Manage Subjects</h1>
            <p class="subtitle">Create and manage all subjects</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Create Subject -->
    <div class="card">
        <h2>Create New Subject</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Subject Name</label>
                    <input type="text" name="name" required placeholder="e.g. Operating Systems">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="">-- Choose Department --</option>
                        <option value="BCA">BCA</option>
                        <option value="BBA">BBA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="">-- Select Semester --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>">Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 22px;">
                <button type="submit" name="create_subject" class="btn">Create Subject</button>
            </div>
        </form>
    </div>

    <!-- Existing Subjects -->
    <div class="card">
        <h2>Existing Subjects (<?= count($subjects) ?>)</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">No subjects found yet</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td><strong><?= htmlspecialchars($sub['name']) ?></strong></td>
                                <td><?= htmlspecialchars($sub['department']) ?></td>
                                <td>Sem <?= $sub['semester'] ?></td>
                                <td><?= date('d M Y', strtotime($sub['created_at'])) ?></td>
                                <td>
                                    <a href="view-questions.php?subject_id=<?= $sub['id'] ?>" class="btn-secondary">
                                        View Questions
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
