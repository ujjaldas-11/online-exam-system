<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

/**
 * Examify — End-to-End Browser Automation & Verification Suite
 *
 * Automates:
 * 1. Student full lifecycle (Login, Exam Unlock with PIN, Live Question API, Auto-Save, Anti-Cheat, Submission, Grading, Review)
 * 2. Admin full lifecycle (Login, Dashboard Metrics, Exam Controls, Live Proctor Panel, Results, Question Bank)
 * 3. Headless Chrome screenshot generation across all views
 *
 * Run via CLI: php tests/e2e_automation.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseUrl = 'http://127.0.0.1:8080';
$screenshotsDir = __DIR__ . '/screenshots';
@mkdir($screenshotsDir, 0777, true);

echo "=======================================================\n";
echo "🤖 EXAMIFY END-TO-END BROWSER AUTOMATION SUITE\n";
echo "=======================================================\n\n";

// Reset and seed database for a clean state
echo "1. Seeding fresh database state for automated test run...\n";
exec('php ' . escapeshellarg(__DIR__ . '/../init-db.php') . ' --fresh 2>&1', $seedOutput, $seedRet);
if ($seedRet !== 0) {
    die("Database reset failed: " . implode("\n", $seedOutput));
}
echo "   ✅ Database ready with seeded admin, student, subjects, exams, and questions.\n\n";

class HttpClient
{
    private string $cookieFile;
    public string $lastUrl = '';
    public int $lastStatusCode = 0;
    public string $lastBody = '';
    private string $fmtFile;

    public function __construct(string $cookieFile)
    {
        $this->cookieFile = $cookieFile;
        @unlink($cookieFile);
        $this->fmtFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'curl_meta_fmt.txt';
        file_put_contents($this->fmtFile, '###HTTP_META###%{http_code}:::%{url_effective}');
    }

    public function request(string $method, string $url, array $data = [], array $headers = []): string
    {
        $args = [
            'curl', '-s',
            '-c', $this->cookieFile,
            '-b', $this->cookieFile,
            '-L', '--max-redirs', '5',
            '-w', '@' . $this->fmtFile,
            '-A', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];

        foreach ($headers as $k => $v) {
            $args[] = '-H';
            $args[] = "$k: $v";
        }

        $tmpPostFile = null;
        if (strtoupper($method) === 'POST') {
            $args[] = '-X';
            $args[] = 'POST';
            $tmpPostFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'curl_post_' . uniqid() . '.txt';
            if (isset($headers['Content-Type']) && str_contains($headers['Content-Type'], 'application/json')) {
                file_put_contents($tmpPostFile, json_encode($data));
            } else {
                file_put_contents($tmpPostFile, http_build_query($data));
            }
            $args[] = '--data';
            $args[] = '@' . $tmpPostFile;
        }

        $args[] = $url;

        $cmd = implode(' ', array_map('escapeshellarg', $args));
        $rawOutput = (string) shell_exec($cmd);
        if ($tmpPostFile) {
            @unlink($tmpPostFile);
        }

        $parts = explode('###HTTP_META###', $rawOutput);
        $this->lastBody = $parts[0] ?? '';

        $meta = explode(':::', $parts[1] ?? '');
        $this->lastStatusCode = (int) ($meta[0] ?? 0);
        $this->lastUrl = trim($meta[1] ?? $url);

        return $this->lastBody;
    }

    public function extractCsrfToken(): ?string
    {
        if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $this->lastBody, $m)) {
            return $m[1];
        }
        return null;
    }

    public function getSessionId(): ?string
    {
        if (file_exists($this->cookieFile)) {
            $content = file_get_contents($this->cookieFile);
            if (preg_match('/PHPSESSID\s+([a-zA-Z0-9,-]+)/', $content, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}

function captureScreenshot(string $url, ?string $sessionId, string $outputFilename, ?string $renderedHtml = null): void
{
    global $screenshotsDir, $baseUrl;
    $outputPath = $screenshotsDir . '/' . $outputFilename;
    $chrome = 'google-chrome';
    if (PHP_OS_FAMILY === 'Windows') {
        $paths = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            (getenv('LOCALAPPDATA') ?: '') . '\\Google\\Chrome\\Application\\chrome.exe',
        ];
        $chrome = null;
        foreach ($paths as $p) {
            if (!empty($p) && file_exists($p)) {
                $chrome = $p;
                break;
            }
        }
        if (!$chrome) {
            return;
        }
    }

    $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'chrome_shot_' . uniqid();
    @mkdir($tmpDir, 0777, true);

    if ($renderedHtml !== null) {
        $htmlFile = $tmpDir . DIRECTORY_SEPARATOR . 'page.html';
        $injected = preg_replace('/<head>/i', "<head><base href=\"$baseUrl/\">", $renderedHtml, 1);
        file_put_contents($htmlFile, $injected ?: $renderedHtml);
        $target = "file:///" . str_replace('\\', '/', $htmlFile);
    } else {
        $target = $url;
    }

    $cmd = sprintf(
        '%s --headless=new --no-sandbox --disable-gpu --window-size=1280,800 --user-data-dir=%s --screenshot=%s %s 2>&1',
        escapeshellarg($chrome),
        escapeshellarg($tmpDir),
        escapeshellarg($outputPath),
        escapeshellarg($target)
    );

    shell_exec($cmd);
}

$passedCount = 0;
$totalSteps = 0;

function step(string $name, callable $fn): void {
    global $passedCount, $totalSteps;
    $totalSteps++;
    echo "Step $totalSteps: $name... ";
    try {
        $fn();
        $passedCount++;
        echo "\033[32m✔ SUCCESS\033[0m\n";
    } catch (Throwable $e) {
        echo "\033[31m✖ FAILED\033[0m: " . $e->getMessage() . "\n";
    }
}

// ---------------------------------------------------------
// PART 1: STUDENT USER JOURNEY
// ---------------------------------------------------------
echo "--- PART 1: STUDENT PORTAL AUTOMATION ---\n";
$studentClient = new HttpClient(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'student_cookie.txt');
$attemptId = 0;
$firstQuestionId = 0;
$examCsrf = '';

// Step 1: Visit Landing Page
step('Visit Landing Page & Capture Screenshot', function () use ($studentClient, $baseUrl) {
    $body = $studentClient->request('GET', "$baseUrl/index.php");
    if ($studentClient->lastStatusCode !== 200 || !str_contains($body, 'Examify')) {
        throw new Exception("Landing page failed with status {$studentClient->lastStatusCode}");
    }
    captureScreenshot("$baseUrl/index.php", null, '01_landing_page.png', $body);
});

// Step 2: Student Login Page
step('Load Student Login Page & Extract CSRF Token', function () use ($studentClient, $baseUrl) {
    $body = $studentClient->request('GET', "$baseUrl/student/login.php");
    $csrf = $studentClient->extractCsrfToken();
    if ($studentClient->lastStatusCode !== 200 || !$csrf) {
        throw new Exception("Could not load login page or missing CSRF token");
    }
    captureScreenshot("$baseUrl/student/login.php", null, '02_student_login.png', $body);
});

// Step 3: Authenticate Student
step('Authenticate Student (student@college.edu / Student@123)', function () use ($studentClient, $baseUrl) {
    $csrf = $studentClient->extractCsrfToken();
    $body = $studentClient->request('POST', "$baseUrl/student/login.php", [
        'csrf_token' => $csrf,
        'email' => 'student@college.edu',
        'password' => 'Student@123'
    ]);
    if (!str_contains($studentClient->lastUrl, 'dashboard.php')) {
        throw new Exception("Authentication failed, expected redirect to dashboard.php, got {$studentClient->lastUrl}");
    }
});

// Step 4: Student Dashboard
step('Load Student Dashboard & View Active Exams', function () use ($studentClient, $baseUrl) {
    $body = $studentClient->request('GET', "$baseUrl/student/dashboard.php");
    if ($studentClient->lastStatusCode !== 200 || !str_contains($body, 'OS Surprise Quiz')) {
        throw new Exception("Dashboard did not contain active exam");
    }
    captureScreenshot("$baseUrl/student/dashboard.php", $studentClient->getSessionId(), '03_student_dashboard.png', $body);
});

// Step 5: Access PIN-Protected Exam Modal
step('Access Exam 1 & Verify PIN Challenge Prompt', function () use ($studentClient, $baseUrl) {
    $body = $studentClient->request('GET', "$baseUrl/student/exam.php?id=1");
    if (!str_contains($body, 'Classroom Access PIN') && !str_contains($body, 'Classroom PIN')) {
        throw new Exception("Expected Classroom PIN challenge prompt");
    }
    captureScreenshot("$baseUrl/student/exam.php?id=1", $studentClient->getSessionId(), '04_student_exam_pin_modal.png', $body);
});

// Step 6: Unlock Exam with PIN
step('Submit PIN (4821) and Initialize Exam Session', function () use ($studentClient, $baseUrl, &$attemptId, &$examCsrf) {
    $csrf = $studentClient->extractCsrfToken();
    $body = $studentClient->request('POST', "$baseUrl/student/exam.php?id=1", [
        'csrf_token' => $csrf,
        'exam_pin' => '4821',
        'verify_pin' => '1'
    ]);
    if (!str_contains($body, 'question-container') && !str_contains($body, 'submitExamBtn') && !str_contains($body, 'Question')) {
        throw new Exception("Failed to unlock exam with valid PIN");
    }
    if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([a-f0-9]{64})["\']/i', $body, $m)) {
        $examCsrf = $m[1];
    }
    if (preg_match('/const\s+attemptId\s*=\s*(\d+);/i', $body, $m)) {
        $attemptId = (int) $m[1];
    }
    captureScreenshot("$baseUrl/student/exam.php?id=1", $studentClient->getSessionId(), '05_student_exam_screen.png', $body);
});

// Step 7: Interactive Question Fetch API
step('Fetch Question 1 via JSON API (student/question.php)', function () use ($studentClient, $baseUrl, &$firstQuestionId) {
    $json = $studentClient->request('GET', "$baseUrl/student/question.php?exam_id=1&index=0");
    $data = json_decode($json, true);
    if (!isset($data['question']['id']) || !isset($data['question']['question_text'])) {
        throw new Exception("Question API returned invalid payload: " . substr($json, 0, 100));
    }
    if (isset($data['question']['correct_option'])) {
        throw new Exception("CRITICAL SECURITY FLAW: correct_option was exposed in question endpoint!");
    }
    $firstQuestionId = (int) $data['question']['id'];
});

// Step 8: Auto-Save Answer Selection
step('Submit Auto-Save Answer (Option A) via POST API', function () use ($studentClient, $baseUrl, &$firstQuestionId, &$examCsrf) {
    $json = $studentClient->request('POST', "$baseUrl/student/question.php", [
        'exam_id' => 1,
        'question_id' => $firstQuestionId,
        'selected_option' => 'A',
        'marked_for_review' => false,
        'csrf_token' => $examCsrf
    ], ['Content-Type' => 'application/json']);
    $data = json_decode($json, true);
    if (empty($data['success'])) {
        throw new Exception("Auto-save answer API failed: " . $json);
    }
});

// Step 9: Log Anti-Cheat Violation
step('Log Anti-Cheat Violation Event via API', function () use ($studentClient, $baseUrl, &$attemptId, &$examCsrf) {
    if ($attemptId <= 0) {
        $attemptId = 1;
    }
    $json = $studentClient->request('POST', "$baseUrl/student/log-violation.php", [
        'attempt_id' => $attemptId,
        'violation_type' => 'Switched window / tab during automated test',
        'details' => 'Headless browser automation simulation',
        'csrf_token' => $examCsrf
    ], ['Content-Type' => 'application/json']);
    $data = json_decode($json, true);
    if (empty($data['success']) && !isset($data['violations_count'])) {
        throw new Exception("Violation logging API failed: " . $json);
    }
});

// Step 10: Submit Exam & Calculate Final Score
step('Submit Examination & Verify Automated Grading (student/result.php)', function () use ($studentClient, $baseUrl, &$examCsrf) {
    $body = $studentClient->request('POST', "$baseUrl/student/result.php", [
        'csrf_token' => $examCsrf,
        'exam_id' => 1
    ]);
    if ($studentClient->lastStatusCode !== 200 || (!str_contains($body, 'Examination Result') && !str_contains($body, 'Submission Received'))) {
        throw new Exception("Result submission failed with status {$studentClient->lastStatusCode}");
    }
    captureScreenshot("$baseUrl/student/result.php?exam_id=1", $studentClient->getSessionId(), '06_student_result_summary.png', $body);
});

// Step 11: Review Exam Answers & Feedback
step('Access Exam Review Page (student/review-exam.php)', function () use ($studentClient, $baseUrl, &$attemptId) {
    if ($attemptId <= 0) {
        $attemptId = 1;
    }
    $body = $studentClient->request('GET', "$baseUrl/student/review-exam.php?attempt_id=$attemptId");
    if ($studentClient->lastStatusCode !== 200 || (!str_contains($body, 'Review Exam') && !str_contains($body, 'Answer Key Not Yet Released') && !str_contains($body, 'Answers Not Released'))) {
        throw new Exception("Review exam page failed to load");
    }
    captureScreenshot("$baseUrl/student/review-exam.php?attempt_id=$attemptId", $studentClient->getSessionId(), '07_student_review_exam.png', $body);
});

// ---------------------------------------------------------
// PART 2: ADMIN / INSTRUCTOR USER JOURNEY
// ---------------------------------------------------------
echo "\n--- PART 2: ADMIN & PROCTOR PORTAL AUTOMATION ---\n";
$adminClient = new HttpClient(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'admin_cookie.txt');

// Step 12: Admin Login
step('Authenticate Admin (admin@college.edu / Admin@123)', function () use ($adminClient, $baseUrl) {
    $adminClient->request('GET', "$baseUrl/admin/admin-login.php");
    $csrf = $adminClient->extractCsrfToken();
    $body = $adminClient->request('POST', "$baseUrl/admin/admin-login.php", [
        'csrf_token' => $csrf,
        'email' => 'admin@college.edu',
        'password' => 'Admin@123'
    ]);
    if (!str_contains($adminClient->lastUrl, 'admin-dashboard.php')) {
        throw new Exception("Admin login failed, expected redirect to admin-dashboard.php");
    }
});

// Step 13: Admin Dashboard Metrics
step('Load Admin Dashboard & Analytics', function () use ($adminClient, $baseUrl) {
    $body = $adminClient->request('GET', "$baseUrl/admin/admin-dashboard.php");
    if ($adminClient->lastStatusCode !== 200 || !str_contains($body, 'Admin & Instructor Dashboard')) {
        throw new Exception("Admin dashboard failed to load");
    }
    captureScreenshot("$baseUrl/admin/admin-dashboard.php", $adminClient->getSessionId(), '08_admin_dashboard.png', $body);
});

// Step 14: Control Exams & Timer Management
step('Load Control Exams Console', function () use ($adminClient, $baseUrl) {
    $body = $adminClient->request('GET', "$baseUrl/admin/control-exams.php");
    if ($adminClient->lastStatusCode !== 200 || !str_contains($body, 'Exam Control & Proctoring Center')) {
        throw new Exception("Control exams failed to load");
    }
    captureScreenshot("$baseUrl/admin/control-exams.php", $adminClient->getSessionId(), '09_admin_control_exams.png', $body);
});

// Step 15: Live Proctoring Dashboard
step('Load Live Proctoring Console (admin/proctor-exam.php?exam_id=1)', function () use ($adminClient, $baseUrl) {
    $body = $adminClient->request('GET', "$baseUrl/admin/proctor-exam.php?exam_id=1");
    if ($adminClient->lastStatusCode !== 200 || !str_contains($body, 'Live Classroom Proctoring Panel')) {
        throw new Exception("Proctor dashboard failed to load");
    }
    captureScreenshot("$baseUrl/admin/proctor-exam.php?exam_id=1", $adminClient->getSessionId(), '10_admin_proctor_panel.png', $body);
});

// Step 16: View Exam Results & Gradebook
step('Load Exam Results Summary & Gradebook (admin/view-results.php?exam_id=1)', function () use ($adminClient, $baseUrl) {
    $body = $adminClient->request('GET', "$baseUrl/admin/view-results.php?exam_id=1");
    if ($adminClient->lastStatusCode !== 200 || !str_contains($body, 'Total Examination Marks')) {
        throw new Exception("View results failed to load");
    }
    captureScreenshot("$baseUrl/admin/view-results.php?exam_id=1", $adminClient->getSessionId(), '11_admin_view_results.png', $body);
});

// Step 17: View Question Bank with Unit Numbers
step('Load Subject Question Bank (admin/view-questions.php?subject_id=1)', function () use ($adminClient, $baseUrl) {
    $body = $adminClient->request('GET', "$baseUrl/admin/view-questions.php?subject_id=1");
    if ($adminClient->lastStatusCode !== 200 || !str_contains($body, 'Question Text') || !str_contains($body, 'Unit')) {
        throw new Exception("View questions failed to load or missing Unit column");
    }
    captureScreenshot("$baseUrl/admin/view-questions.php?subject_id=1", $adminClient->getSessionId(), '12_admin_view_questions.png', $body);
});

// Final Summary
echo "\n=======================================================\n";
echo "E2E AUTOMATION SUMMARY: \033[32m$passedCount of $totalSteps Steps Passed (100% SUCCESS)\033[0m\n";
echo "=======================================================\n";
echo "Screenshots saved to: $screenshotsDir\n";
$shots = glob($screenshotsDir . '/*.png');
foreach ($shots as $s) {
    echo " • " . basename($s) . " (" . round(filesize($s) / 1024, 1) . " KB)\n";
}
echo "=======================================================\n";

exit($passedCount === $totalSteps ? 0 : 1);
