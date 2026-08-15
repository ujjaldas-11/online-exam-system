<?php
require_once 'admin-guard.php';
require_once '../config/database.php';



if (!isset($_GET['subject_id'])) {
    die("No subject selected.");
}

$subject_id = (int)$_GET['subject_id'];
$message = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    //  Delete all questions for this subject
    if (isset($_POST['delete_all'])) {
        $delStmt = $pdo->prepare("DELETE FROM questions WHERE subject_id = ?");
        if ($delStmt->execute([$subject_id])) {
            $message = "<div class='alert alert-success'>🗑️ All questions have been successfully deleted.</div>";
        }
    }


}

$subjectStmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$subjectStmt->execute([$subject_id]);
$subject = $subjectStmt->fetch();

if (!$subject) die("Subject not found.");

// Fetch All Questions for this specific subject
$resultsSql = "SELECT question_text FROM questions WHERE subject_id = :subject_id ORDER BY id ASC"; 
$resultsStmt = $pdo->prepare($resultsSql);
$resultsStmt->execute([':subject_id' => $subject_id]);
$all_results = $resultsStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions: <?= htmlspecialchars($subject['name']) ?></title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --danger: #dc2626;
            --danger-hover: #b91c1c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--light); color: var(--dark); line-height: 1.5; }
        
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        .header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); }

        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .card h2 { font-size: 1.15rem; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }

        /* Buttons & Forms */
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; border: none; font-size: 0.95rem; display: inline-block; transition: 0.2s;}
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background: white; border: 1px solid var(--border); color: var(--dark); margin-bottom: 20px; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: var(--danger-hover); }
        
        /* Action Bar for Bulk Updates */
        .action-bar { display: flex; gap: 15px; align-items: center; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; }
        .action-bar form { display: flex; align-items: center; gap: 10px; }
        .mark-input { padding: 6px 10px; border: 1px solid var(--border); border-radius: 4px; width: 80px; font-size: 0.95rem; }

        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f1f5f9; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:hover td { background: #f8fafc; }
        .score { font-weight: 700; color: var(--primary); }

        /* Print Settings */
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .container { padding: 0; max-width: 100%; }
            .card { border: none; padding: 0; box-shadow: none; margin-bottom: 20px; }
            th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <?php include 'admin-navbar.php' ?>
</div>

<div class="container">
    <a href="manage-subjects.php" class="btn btn-outline no-print">← Back to All Subjects</a>

    <div class="header-flex">
        <div>
            <h1><?= htmlspecialchars($subject['name']) ?></h1>
            <p class="subtitle">Department: <?= htmlspecialchars($subject['department']) ?> • Semester <?= $subject['semester'] ?></p>
        </div>
        <a href="manage-questions.php">
                    <button  class="btn btn-primary" style="padding: 6px 12px;" >
                        Upload questions
                    </button>
                </a>
    </div>

    <?= $message ?>

    <?php if (empty($all_results)): ?>
        <div class="card">
            <p style="color:var(--gray); text-align:center;">No questions have been added to this subject yet.</p>
        </div>
    <?php else: ?>

        
        <div class="card">
            <div class="header-flex" style="margin-bottom: 10px;">
                <h2 style="border: none; margin: 0; padding: 0;"> Question Bank (<?= count($all_results) ?> Questions)</h2>
            </div>
            
            <div class="action-bar no-print">
                <a href="manage-questions.php">
                    <button  class="btn btn-primary" style="padding: 6px 12px;" >
                        Upload questions
                    </button>
                </a>
                
                <form method="POST">
                    <button type="submit" name="delete_all" class="btn btn-danger" style="padding: 6px 12px;" onclick="return confirm('⚠️ WARNING: Are you sure you want to delete ALL questions? This action CANNOT be undone!');">
                        🗑️ Delete All Questions
                    </button>
                </form>
            </div>

            <?php
                $search_placeholder = "Search students, roll number, or department...";
                include '../components/searchbar.php';
            ?>

            
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Question Text</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($all_results as $row): 
                        ?>
                        <tr>
                            <td><strong><?= $counter++ ?></strong></td>
                            <td>
                                <?= nl2br(htmlspecialchars($row['question_text'])) ?>
                            </td>
                            <td>
                                <span class="score"><?= $row['marks'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

</body>
</html>