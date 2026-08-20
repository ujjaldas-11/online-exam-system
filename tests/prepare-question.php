<?php
require_once __DIR__ . '/../config/database.php';

echo "Scanning for JSON files in " . __DIR__ . "...\n";

$json_files = glob(__DIR__ . '/*.json');

if (empty($json_files)) {
    echo "No JSON files found in " . __DIR__ . "\n";
    exit(1);
}

foreach ($json_files as $file) {
    $filename = basename($file);
    echo "Processing $filename...\n";

    // Extract subject name (everything before the first '-')
    $parts = explode('-', $filename);
    $subject_name = trim($parts[0]);

    if (empty($subject_name)) {
        echo "  [Skipped] Could not determine subject name from $filename\n";
        continue;
    }

    // Check if subject exists
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = :name");
    $stmt->execute([':name' => $subject_name]);
    $subject = $stmt->fetch();

    if ($subject) {
        $subject_id = $subject['id'];
        echo "  Found existing subject: $subject_name (ID: $subject_id)\n";
    } else {
        // Create the subject if it doesn't exist
        $stmt = $pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES (:name, 'General', 1)");
        $stmt->execute([':name' => $subject_name]);
        $subject_id = $pdo->lastInsertId();
        echo "  Created new subject: $subject_name (ID: $subject_id)\n";
    }

    // Read and parse JSON
    $json_content = file_get_contents($file);
    $questions = json_decode($json_content, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        echo "  [Error] Invalid JSON format in $filename\n";
        continue;
    }

    $inserted_count = 0;

    // Insert questions
    try {
        $pdo->beginTransaction();

        $sql = "INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks)
                VALUES (:subject_id, :q_text, :opt_a, :opt_b, :opt_c, :opt_d, :correct, :marks)";
        $insertStmt = $pdo->prepare($sql);

        foreach ($questions as $q) {
            if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
                continue; // Skip invalid questions
            }

            $insertStmt->execute([
                ':subject_id' => $subject_id,
                ':q_text' => trim(strip_tags($q['question_text'])),
                ':opt_a' => trim(strip_tags($q['option_a'])),
                ':opt_b' => trim(strip_tags($q['option_b'])),
                ':opt_c' => isset($q['option_c']) ? trim(strip_tags($q['option_c'])) : '',
                ':opt_d' => isset($q['option_d']) ? trim(strip_tags($q['option_d'])) : '',
                ':correct' => strtoupper(trim(strip_tags($q['correct_option']))),
                ':marks' => isset($q['marks']) ? (int) $q['marks'] : 1
            ]);
            $inserted_count++;
        }

        $pdo->commit();
        echo "  Successfully inserted $inserted_count questions for subject '$subject_name'.\n";

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "  [Error] Failed to insert questions from $filename: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";
?>
