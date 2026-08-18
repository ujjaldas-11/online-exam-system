<?php 
require_once 'admin-guard.php';
require_once '../config/database.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];

    if (isset($_POST['action']) && $_POST['action'] === 'approve') {
        $reqstmt = $pdo->prepare("SELECT * FROM profile_requests WHERE id = ?");
        $reqstmt->execute([$request_id]);
        $req = $reqstmt->fetch();

        if ($req && $req['status'] === 'pending') {
            $pdo->beginTransaction();   
            try {
                // 1. Update the student
                $updatestmt = $pdo->prepare("UPDATE students SET name = ?, roll_number = ?, department = ?, semester = ? WHERE id = ?");
                $updateSuccess = $updatestmt->execute([$req['new_name'], $req['new_roll_no'], $req['new_department'], $req['new_semester'], $req['student_id']]);

                // 2. Update the request status
                $statusStmt = $pdo->prepare("UPDATE profile_requests SET status = 'approved' WHERE id = ?");
                $statusSuccess = $statusStmt->execute([$request_id]);

                // Check if BOTH succeeded before committing
                if ($updateSuccess && $statusSuccess) {
                    $pdo->commit();     
                    $message = "Student profile updated successfully!";
                } else {
                    $pdo->rollBack();
                    $error = "Failed to execute update queries. Check your database constraints.";
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                // This will now print the EXACT error if the database complains!
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }    
    elseif (isset($_POST['action']) && $_POST['action'] === 'reject') {
        $pdo->prepare("UPDATE profile_requests SET status ='rejected' WHERE id = ?")->execute([$request_id]);
        $message = "Request has been rejected.";
    }
}

// FIXED: Variable named $requests (plural) so your HTML loop works
$requests = $pdo->query("
    SELECT r.*, s.name as old_name, s.roll_number as old_roll, s.department as old_dept, s.semester as old_sem 
    FROM profile_requests r 
    JOIN students s ON r.student_id = s.id 
    WHERE r.status = 'pending' 
    ORDER BY r.request_date ASC
")->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests • Examify</title>
</head>

<body>
    <?php include 'admin-navbar.php' ?>
    <!-- Show Success or Error Messages -->
    <?php if ($message): ?>
        <div style="background: green; color: white; padding: 10px; margin-bottom: 15px;"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: red; color: white; padding: 10px; margin-bottom: 15px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="table-wrap">

        <table cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f1f5f9;">
                <tr>
                    <th>Student</th>
                    <th>Current Data</th>
                    <th>Requested Data</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: gray;">No pending requests.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= htmlspecialchars($req['old_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($req['old_roll']) ?> | <?= htmlspecialchars($req['old_dept']) ?> | Sem <?= htmlspecialchars($req['old_sem']) ?>
                            </td>
                            <td style="color: blue;">
                                <?= htmlspecialchars($req['new_name']) ?><br>
                                <?= htmlspecialchars($req['new_roll_no']) ?> | <?= htmlspecialchars($req['new_department']) ?> | Sem <?= htmlspecialchars($req['new_semester']) ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                    <button type="submit" name="action" value="approve" style="background: green; color: white; padding: 5px 10px; border: none; cursor: pointer;">Approve</button>
                                    <button type="submit" name="action" value="reject" style="background: red; color: white; padding: 5px 10px; border: none; cursor: pointer;">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>