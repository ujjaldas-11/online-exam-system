<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/sanitize.php';

/**
 * CSV Service
 * Centralizes CSV export streaming, formula sanitization, and upload validation.
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
}
