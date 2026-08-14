<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

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
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        
        /* Layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert.success { background: #dcfce7; color: var(--success); }
        .alert.error { background: #fee2e2; color: var(--error); }

        /* Cards */
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 {
            font-size: 1.15rem;
            margin-bottom: 16px;
        }

        /* Form */
        .form-group { margin-bottom: 14px; }
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
            margin-top: 6px;
        }
        .btn:hover { background: #1d4ed8; }

        /* Table */
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

    </style>
</head>
<body>


<?php include 'admin-navbar.php' ?>

<div class="container">
    <h1>Manage Subjects</h1>
    <p class="subtitle">Create and view all subjects</p>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Create Subject -->
    <div class="card">
        <h2>Create New Subject</h2>
        <form method="POST">
            <div class="form-group">
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

            <button type="submit" name="create_subject" class="btn">Create Subject</button>
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
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:var(--gray);">No subjects found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><?= $sub['id'] ?></td>
                                <td><?= htmlspecialchars($sub['name']) ?></td>
                                <td><?= htmlspecialchars($sub['department']) ?></td>
                                <td>Sem <?= $sub['semester'] ?></td>
                                <td><?= date('d M Y', strtotime($sub['created_at'])) ?></td>
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