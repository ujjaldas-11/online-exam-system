<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/sanitize.php';

if (!function_exists('mb_strlen')) {
    function mb_strlen(string $string, ?string $encoding = null): int { return strlen($string); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }
}
if (!function_exists('mb_strpos')) {
    function mb_strpos(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false {
        return strpos($haystack, $needle, $offset);
    }
}

/**
 * CSV & Spreadsheet Service
 * Centralizes CSV and XLSX export streaming, formula sanitization, and upload validation.
 */
class CsvService
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB limit
    private const ALLOWED_EXTENSIONS = ['csv', 'txt'];
    private const ALLOWED_MIMES = [
        'text/plain',
        'text/csv',
        'application/csv',
        'text/x-csv',
        'application/vnd.ms-excel',
        'text/comma-separated-values',
        'application/octet-stream',
    ];

    /**
     * Standard Question Bank Column Headers
     */
    public const QUESTION_HEADERS = [
        'Question Text',
        'Unit Number',
        'Option A',
        'Option B',
        'Option C',
        'Option D',
        'Correct Option',
        'Question Type'
    ];

    /**
     * Stream a CSV file download directly to the client.
     *
     * @param string $filename Name of downloaded file (e.g. students_roster.csv)
     * @param string[] $headers Column headers
     * @param array $rows Array of records (associative or indexed)
     * @param (callable(array): array)|null $rowFormatter Optional mapper transforming row before output
     */
    public static function export(
        string $filename,
        array $headers,
        array $rows,
        ?callable $rowFormatter = null
    ): void {
        if (!str_ends_with(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }

        // Output UTF-8 BOM for Microsoft Excel compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Write header
        fputcsv($out, array_map('strval', $headers));

        // Write rows
        foreach ($rows as $row) {
            $formattedRow = $rowFormatter !== null ? $rowFormatter($row) : (array)$row;
            $sanitizedRow = array_map(function ($val) {
                if ($val === null) {
                    return '';
                }
                return sanitize_csv_value((string)$val);
            }, $formattedRow);
            fputcsv($out, $sanitizedRow);
        }

        fclose($out);
        exit;
    }

    /**
     * Stream an Excel XLSX file download directly to the client.
     *
     * @param string $filename Name of downloaded file (e.g. questions_template.xlsx)
     * @param string[] $headers Column headers
     * @param array $rows Array of records (associative or indexed)
     * @param (callable(array): array)|null $rowFormatter Optional mapper transforming row before output
     */
    public static function exportXlsx(
        string $filename,
        array $headers,
        array $rows,
        ?callable $rowFormatter = null
    ): void {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        require_once __DIR__ . '/../lib/simplexlsxgen/SimpleXLSXGen.php';

        // Format header row with bold text for SimpleXLSXGen
        $headerRow = array_map(fn($h) => '<b>' . htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') . '</b>', $headers);

        $xlsxRows = [$headerRow];
        foreach ($rows as $row) {
            $formattedRow = $rowFormatter !== null ? $rowFormatter($row) : (array)$row;
            $sanitizedRow = array_map(function ($val) {
                if ($val === null) {
                    return '';
                }
                return sanitize_csv_value((string)$val);
            }, $formattedRow);
            $xlsxRows[] = array_values($sanitizedRow);
        }

        $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($xlsxRows);

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo (string)$xlsx;
        exit;
    }

    /**
     * Export a list of question records to CSV or XLSX format.
     *
     * @param string $filename Base filename without extension
     * @param array $questions Array of question rows from database
     * @param string $format 'csv' or 'xlsx'
     */
    public static function exportQuestions(string $filename, array $questions, string $format = 'csv'): void
    {
        $headers = self::QUESTION_HEADERS;
        $formatter = function (array $q): array {
            return [
                $q['question_text'] ?? '',
                $q['unit_number'] ?? 1,
                $q['option_a'] ?? '',
                $q['option_b'] ?? '',
                $q['option_c'] ?? '',
                $q['option_d'] ?? '',
                $q['correct_option'] ?? 'A',
                $q['question_type'] ?? 'single'
            ];
        };

        if (strtolower($format) === 'xlsx') {
            self::exportXlsx($filename, $headers, $questions, $formatter);
        } else {
            self::export($filename, $headers, $questions, $formatter);
        }
    }

    /**
     * Return high-quality sample template question rows covering all 5 supported question types.
     *
     * @return array
     */
    public static function getSampleQuestionRows(): array
    {
        return [
            [
                'question_text' => 'Which data structure operates on a Last-In, First-Out (LIFO) basis?',
                'unit_number' => 1,
                'option_a' => 'Queue',
                'option_b' => 'Stack',
                'option_c' => 'Array',
                'option_d' => 'Binary Tree',
                'correct_option' => 'B',
                'question_type' => 'single'
            ],
            [
                'question_text' => 'Which of the following are standard inter-process communication (IPC) mechanisms in UNIX-like systems? (Select all that apply)',
                'unit_number' => 1,
                'option_a' => 'Message Queues',
                'option_b' => 'Shared Memory',
                'option_c' => 'Pipes',
                'option_d' => 'Floating-Point Registers',
                'correct_option' => 'A,B,C',
                'question_type' => 'multiple'
            ],
            [
                'question_text' => "Scenario: A high-frequency trading server experiences severe throughput degradation due to lock contention on shared queues. Which architectural pattern should the engineers evaluate?",
                'unit_number' => 2,
                'option_a' => 'Lock-free ring buffers with atomic CAS',
                'option_b' => 'Coarse-grained recursive mutexes',
                'option_c' => 'Single-threaded synchronous I/O',
                'option_d' => 'Global Interpreter Lock',
                'correct_option' => 'A',
                'question_type' => 'case_study'
            ],
            [
                'question_text' => "Assertion (A): Virtual memory paging completely eliminates external fragmentation.\nReason (R): In paging, physical memory is partitioned into uniform, fixed-size page frames.",
                'unit_number' => 3,
                'option_a' => 'Both A and R are true, and R is the correct explanation of A',
                'option_b' => 'Both A and R are true, but R is NOT the correct explanation of A',
                'option_c' => 'A is true, but R is false',
                'option_d' => 'A is false, but R is true',
                'correct_option' => 'A',
                'question_type' => 'assertion_reason'
            ],
            [
                'question_text' => "Match the disk scheduling algorithms with their operational behaviors:\n1. FCFS - P. Services nearest request\n2. SSTF - Q. Strict arrival order\n3. SCAN - R. Elevates in one direction then reverses",
                'unit_number' => 4,
                'option_a' => '1-Q, 2-P, 3-R',
                'option_b' => '1-P, 2-Q, 3-R',
                'option_c' => '1-R, 2-P, 3-Q',
                'option_d' => '1-Q, 2-R, 3-P',
                'correct_option' => 'A',
                'question_type' => 'matching'
            ]
        ];
    }

    /**
     * Trigger immediate download of sample questions template in CSV or XLSX format.
     *
     * @param string $format 'csv' or 'xlsx'
     */
    public static function downloadSampleQuestionsTemplate(string $format = 'csv'): void
    {
        $rows = self::getSampleQuestionRows();
        self::exportQuestions('questions_template', $rows, $format);
    }

    /**
     * Validate an uploaded CSV file from $_FILES.
     * Returns null if valid, or an error message string if invalid.
     *
     * @param array $file Entry from $_FILES (e.g. $_FILES['csv_file'])
     * @param int $maxSizeBytes Maximum allowed file size (default 5MB)
     * @return string|null Error message or null on success
     */
    public static function validateUploadedCsv(array $file, int $maxSizeBytes = self::MAX_FILE_SIZE): ?string
    {
        if (empty($file['name'])) {
            return 'No file was uploaded.';
        }

        $fileError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_OK) {
            return 'File upload error code: ' . $fileError;
        }

        $fileSize = (int)($file['size'] ?? 0);
        if ($fileSize <= 0) {
            return 'Uploaded file is empty.';
        }

        if ($fileSize > $maxSizeBytes) {
            return 'Uploaded file too large. Maximum size allowed is ' . round($maxSizeBytes / (1024 * 1024), 1) . 'MB.';
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath)) {
            return 'Uploaded file verification failed.';
        }

        $fileExt = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, self::ALLOWED_EXTENSIONS, true)) {
            return 'Invalid file extension. Only .csv and .txt files are allowed.';
        }

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpPath);
            if ($mimeType && !in_array($mimeType, self::ALLOWED_MIMES, true)) {
                return "Invalid file type ($mimeType). Only CSV files are allowed.";
            }
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($tmpPath);
            if ($mimeType && !in_array($mimeType, self::ALLOWED_MIMES, true)) {
                return "Invalid file type ($mimeType). Only CSV files are allowed.";
            }
        }

        return null;
    }

    /**
     * Normalizes and repairs a parsed question CSV row.
     * Handles edge cases such as unquoted commas inside options (e.g. assertion_reason or matching),
     * unquoted comma-separated multiple answers, or unquoted commas in question text.
     *
     * Returns an array in standard 7 or 8-column format:
     * [0 => question_text, 1 => unit_number, 2 => opt_a, 3 => opt_b, 4 => opt_c, 5 => opt_d, 6 => correct, (7 => type)]
     *
     * @param array $data Raw row returned by fgetcsv
     * @return array Normalized row
     */
    public static function normalizeQuestionRow(array $data): array
    {
        $count = count($data);
        if ($count <= 6) {
            return $data;
        }

        if ($count === 7 || $count === 8) {
            if (is_numeric(trim((string)$data[1]))) {
                return $data;
            }
        }

        // 1. Repair unquoted commas in question text if unit_number is not at index 1
        if (!is_numeric(trim((string)$data[1]))) {
            $uIdx = -1;
            for ($i = 1; $i < count($data) - 5; $i++) {
                $val = trim((string)$data[$i]);
                if (is_numeric($val) && (int)$val >= 1 && (int)$val <= 20) {
                    $uIdx = $i;
                    break;
                }
            }
            if ($uIdx > 1) {
                $qText = implode(', ', array_map('trim', array_slice($data, 0, $uIdx)));
                $uNum = trim((string)$data[$uIdx]);
                $data = array_merge([$qText, $uNum], array_slice($data, $uIdx + 1));
            }
        }

        if (count($data) === 7 || count($data) === 8) {
            return $data;
        }

        $validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];

        // 2. Extract trailing Question Type if present
        $detectedType = null;
        $lastVal = strtolower(trim((string)end($data)));
        if (in_array($lastVal, $validTypes, true)) {
            $detectedType = $lastVal;
            array_pop($data);
        }

        // 3. Extract trailing Correct Option(s)
        $allowedLetters = ['A', 'B', 'C', 'D'];
        $correctLetters = [];

        while (count($data) > 6) {
            $candidate = strtoupper(trim((string)end($data)));
            if (in_array($candidate, $allowedLetters, true)) {
                array_unshift($correctLetters, array_pop($data));
                if ($detectedType && $detectedType !== 'multiple') {
                    break;
                }
            } elseif (preg_match('/^[A-D](\s*,\s*[A-D])+$/', $candidate)) {
                $tokens = array_filter(array_map('trim', explode(',', $candidate)));
                foreach ($tokens as $t) {
                    if (in_array($t, $allowedLetters, true) && !in_array($t, $correctLetters, true)) {
                        $correctLetters[] = $t;
                    }
                }
                array_pop($data);
                break;
            } else {
                break;
            }
        }

        if (empty($correctLetters)) {
            $rawCorrect = strtoupper(trim((string)array_pop($data)));
        } else {
            $rawCorrect = implode(',', $correctLetters);
        }

        $qText = $data[0] ?? '';
        $uNum = $data[1] ?? '1';
        $middle = array_values(array_slice($data, 2));

        $opts = [];
        if (count($middle) === 4) {
            $opts = $middle;
        } elseif (count($middle) > 4) {
            // Check for assertion_reason partition
            $isAssertion = ($detectedType === 'assertion_reason' || stripos($qText, 'assertion') !== false);
            if ($isAssertion) {
                $starts = [];
                foreach ($middle as $idx => $frag) {
                    $trimmed = trim($frag);
                    if ($idx === 0) {
                        $starts[0] = $idx;
                    } elseif (count($starts) === 1 && preg_match('/^both\b/i', $trimmed)) {
                        $starts[1] = $idx;
                    } elseif (count($starts) === 2 && preg_match('/^(\(?a\)?|assertion)\s+is\s+true\b/i', $trimmed)) {
                        $starts[2] = $idx;
                    } elseif (count($starts) === 3 && preg_match('/^(\(?a\)?|assertion)\s+is\s+false\b/i', $trimmed)) {
                        $starts[3] = $idx;
                    }
                }
                if (count($starts) === 4) {
                    for ($i = 0; $i < 4; $i++) {
                        $from = $starts[$i];
                        $to = ($i < 3) ? $starts[$i + 1] : count($middle);
                        $slice = array_slice($middle, $from, $to - $from);
                        $opts[] = implode(', ', array_map('trim', $slice));
                    }
                    if (!$detectedType) {
                        $detectedType = 'assertion_reason';
                    }
                }
            }

            // If not partitioned, check if fragments evenly divide by 4
            if (empty($opts) && count($middle) % 4 === 0) {
                $chunkSize = (int)(count($middle) / 4);
                for ($i = 0; $i < 4; $i++) {
                    $slice = array_slice($middle, $i * $chunkSize, $chunkSize);
                    $opts[] = implode(', ', array_map('trim', $slice));
                }
            }

            // Generic fallback if still unpartitioned
            if (empty($opts)) {
                $opts[0] = $middle[0] ?? '';
                $opts[1] = $middle[1] ?? '';
                $opts[2] = $middle[2] ?? '';
                $opts[3] = implode(', ', array_map('trim', array_slice($middle, 3)));
            }
        } else {
            $opts = array_pad($middle, 4, '');
        }

        $result = [
            $qText,
            $uNum,
            $opts[0] ?? '',
            $opts[1] ?? '',
            $opts[2] ?? '',
            $opts[3] ?? '',
            $rawCorrect
        ];

        if ($detectedType !== null) {
            $result[] = $detectedType;
        }

        return $result;
    }
}
