<?php

/**
 * Examify PDF Generation Service
 * Powered by pure-PHP FPDF library (lib/fpdf/fpdf.php)
 * Zero external OS dependencies; works seamlessly on Windows/Linux LAN environments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class ExamifyPdf extends FPDF
{
    public string $examTitle = '';
    public string $metaInfo = '';
    public string $footerSubtext = 'Official Academic Assessment Record';

    public function Header(): void
    {
        // Dark slate header background band
        $this->SetFillColor(30, 41, 59);
        $this->Rect(0, 0, $this->GetPageWidth(), 24, 'F');

        $this->SetY(4);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 7, 'BENGAL INSTITUE OF SCIENCE & TECHNOLOGY', 0, 1, 'C');
        $this->Cell(0, 6, 'EXAMIFY - COLLEGE EXAMINATION PORTAL', 0, 1, 'C');

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(203, 213, 225);
        $this->Cell(0, 5, 'Official Academic Assessment Record', 0, 1, 'C');

        $this->SetTextColor(30, 41, 59);
        $this->SetY(30);

        if ($this->examTitle) {
            $titleStr = strtoupper($this->examTitle);
            $fontSize = 13;
            $this->SetFont('Helvetica', 'B', $fontSize);
            while ($this->GetStringWidth($titleStr) > 185 && $fontSize > 9) {
                $fontSize -= 0.5;
                $this->SetFont('Helvetica', 'B', $fontSize);
            }
            while ($this->GetStringWidth($titleStr) > 185 && mb_strlen($titleStr) > 10) {
                $titleStr = mb_substr($titleStr, 0, -1);
            }
            $this->Cell(0, 7, $titleStr, 0, 1, 'L');
        }

        if ($this->metaInfo) {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 5, $this->metaInfo, 0, 1, 'L');
            $this->SetTextColor(30, 41, 59);
        }

        $this->Ln(2);

        // Header bottom separator line
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
        $this->Ln(4);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184);

        $dateStr = 'Generated: ' . date('d M Y, h:i A');
        $this->Cell(0, 10, $dateStr, 0, 0, 'L');
        $this->Cell(0, 10, $this->footerSubtext . '  |  Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

class ExamifyScorecardPdf extends FPDF
{
    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(148, 163, 184);

        $dateStr = 'Generated: ' . date('d M Y, h:i A');
        $this->Cell(0, 10, $dateStr, 0, 0, 'L');
        $this->Cell(0, 10, 'Official Student Assessment Grade Sheet  |  Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

class PdfService
{
    /**
     * 1. Generate institutional exam results summary report PDF (Admin View Results Page)
     */
    public static function generateExamResultsPdf(array $exam, array $allResults, string $mode = 'I'): string 
    {
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->examTitle = (string)($exam['title'] ?? 'Examination Results');
        $dept = (string)($exam['department'] ?? 'General');
        $sem = (string)($exam['semester'] ?? 'All');
        $author = (string)($exam['creator_name'] ?? 'College Faculty');
        $maxMarks = (float)($exam['total_marks'] ?? 0);
        $totalSubmissions = count($allResults);

        $pdf->metaInfo = "Department: $dept (Sem $sem)  |  Maximum Marks: $maxMarks  |  Total Candidates: $totalSubmissions  |  Instructor: $author";
        $pdf->AddPage();

        $passedCount = 0; $failedCount = 0; $highestScore = 0.0; $totalScoreSum = 0.0;

        foreach ($allResults as $res) {
            $s = (float)($res['score'] ?? 0);
            $totalScoreSum += $s;
            if ($s > $highestScore) $highestScore = $s;
            $pct = ($maxMarks > 0) ? ($s / $maxMarks) * 100 : 0;
            if ($pct >= 50.0) { $passedCount++; } else { $failedCount++; }
        }

        $avgScore = ($totalSubmissions > 0) ? round($totalScoreSum / $totalSubmissions, 2) : 0.0;
        $passRate = ($totalSubmissions > 0) ? round(($passedCount / $totalSubmissions) * 100, 1) : 0;

        $kpiStartY = $pdf->GetY();
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Rect(10, $kpiStartY, 190, 20, 'DF');

        $kpiY = $kpiStartY + 2.5;
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(100, 116, 139);

        $colW = 190 / 5; 
        $pdf->SetXY(10, $kpiY);
        $pdf->Cell($colW, 4, 'CANDIDATES', 0, 0, 'C');
        $pdf->Cell($colW, 4, 'PASSED (>=50%)', 0, 0, 'C');
        $pdf->Cell($colW, 4, 'FAILED (<50%)', 0, 0, 'C');
        $pdf->Cell($colW, 4, 'HIGHEST SCORE', 0, 0, 'C');
        $pdf->Cell($colW, 4, 'CLASS AVERAGE', 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetX(10);
        $pdf->Cell($colW, 9, (string)$totalSubmissions, 0, 0, 'C');
        $pdf->SetTextColor(22, 163, 74); $pdf->Cell($colW, 9, "$passedCount ($passRate%)", 0, 0, 'C');
        $pdf->SetTextColor(220, 38, 38); $pdf->Cell($colW, 9, (string)$failedCount, 0, 0, 'C');
        $pdf->SetTextColor(37, 99, 235); $pdf->Cell($colW, 9, (string)$highestScore . " / $maxMarks", 0, 0, 'C');
        $pdf->SetTextColor(30, 41, 59); $pdf->Cell($colW, 9, (string)$avgScore, 0, 1, 'C');

        $pdf->SetY($kpiStartY + 26);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);

        $wRank = 14; $wRoll = 28; $wName = 56; $wScore = 26; $wPct = 20; $wStatus = 18; $wTime = 28;

        $pdf->Cell($wRank, 8, 'Rank', 1, 0, 'C', true);
        $pdf->Cell($wRoll, 8, 'Roll Number', 1, 0, 'C', true);
        $pdf->Cell($wName, 8, 'Student Name', 1, 0, 'L', true);
        $pdf->Cell($wScore, 8, 'Score', 1, 0, 'C', true);
        $pdf->Cell($wPct, 8, 'Percentage', 1, 0, 'C', true);
        $pdf->Cell($wStatus, 8, 'Result', 1, 0, 'C', true);
        $pdf->Cell($wTime, 8, 'Submitted', 1, 1, 'C', true);

        $pdf->SetFont('Helvetica', '', 9);
        $rank = 1;

        if (empty($allResults)) {
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(190, 12, 'No completed submissions recorded for this examination.', 1, 1, 'C');
        } else {
            foreach ($allResults as $row) {
                if ($pdf->GetY() > 255) {
                    $pdf->AddPage();
                    $pdf->SetY($pdf->GetY() + 2);
                    $pdf->SetFillColor(30, 41, 59);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('Helvetica', 'B', 9);
                    $pdf->Cell($wRank, 8, 'Rank', 1, 0, 'C', true); $pdf->Cell($wRoll, 8, 'Roll Number', 1, 0, 'C', true); $pdf->Cell($wName, 8, 'Student Name', 1, 0, 'L', true); $pdf->Cell($wScore, 8, 'Score', 1, 0, 'C', true); $pdf->Cell($wPct, 8, 'Percentage', 1, 0, 'C', true); $pdf->Cell($wStatus, 8, 'Result', 1, 0, 'C', true); $pdf->Cell($wTime, 8, 'Submitted', 1, 1, 'C', true);
                    $pdf->SetFont('Helvetica', '', 9);
                }

                $fill = ($rank % 2 === 0);
                $pdf->SetFillColor(241, 245, 249);
                $pdf->SetTextColor(30, 41, 59);
                $score = (float)($row['score'] ?? 0);
                $pct = ($maxMarks > 0) ? round(($score / $maxMarks) * 100) : 0;
                $isPass = ($pct >= 50);

                $pdf->Cell($wRank, 7, '#' . $rank++, 1, 0, 'C', $fill);
                $pdf->Cell($wRoll, 7, (string)$row['roll_number'], 1, 0, 'C', $fill);

                $nameStr = (string)$row['name'];
                while ($pdf->GetStringWidth($nameStr) > ($wName - 4) && mb_strlen($nameStr) > 4) { $nameStr = mb_substr($nameStr, 0, -1); }
                if ($nameStr !== (string)$row['name']) { $nameStr .= '..'; }
                
                $pdf->Cell($wName, 7, $nameStr, 1, 0, 'L', $fill);
                $pdf->Cell($wScore, 7, sprintf('%.2f', $score) . " / $maxMarks", 1, 0, 'C', $fill);
                $pdf->Cell($wPct, 7, "$pct%", 1, 0, 'C', $fill);

                if ($isPass) {
                    $pdf->SetTextColor(22, 163, 74); $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($wStatus, 7, 'PASS', 1, 0, 'C', $fill);
                } else {
                    $pdf->SetTextColor(220, 38, 38); $pdf->SetFont('Helvetica', 'B', 9); $pdf->Cell($wStatus, 7, 'FAIL', 1, 0, 'C', $fill);
                }

                $pdf->SetTextColor(30, 41, 59); $pdf->SetFont('Helvetica', '', 8);
                $pdf->Cell($wTime, 7, !empty($row['submitted_at']) ? date('d M, h:i A', strtotime($row['submitted_at'])) : '-', 1, 1, 'C', $fill);
                $pdf->SetFont('Helvetica', '', 9);
            }
        }

        $sigY = max($pdf->GetY() + 16, 238);
        if ($sigY > 252) { $pdf->AddPage(); $sigY = max($pdf->GetY() + 20, 65); }

        $pdf->SetDrawColor(148, 163, 184);
        $pdf->Line(20, $sigY + 12, 75, $sigY + 12); $pdf->Line(135, $sigY + 12, 190, $sigY + 12);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(20, $sigY + 14); $pdf->Cell(55, 4, 'Signature of Course Instructor', 0, 0, 'C');
        $pdf->SetXY(135, $sigY + 14); $pdf->Cell(55, 4, 'Signature of Head of Department', 0, 0, 'C');

        if (ob_get_length()) ob_end_clean();
        if ($mode === 'S') return (string) $pdf->Output('S');
        $pdf->Output($mode, 'Exam_Result_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($exam['title'] ?? 'Report')) . '.pdf');
        exit;
    }

    /**
     * 2. Generate Printable Offline Exam Paper (Admin: Print questions without answers)
     */
    public static function generateOfflineExamPaperPdf(array $exam, array $questions, string $mode = 'I'): string 
    {
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->examTitle = (string)($exam['title'] ?? 'Examination Paper');
        $pdf->footerSubtext = 'Official Offline Question Paper';
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        // Header Info Box (Department, Instructor, Target Units, Duration)
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Rect(10, $pdf->GetY(), 190, 22, 'DF');

        $pdf->SetY($pdf->GetY() + 3);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        
        $dept = ($exam['department'] ?? 'General') . ' (Sem ' . ($exam['semester'] ?? 'All') . ')';
        $units = ($exam['target_units'] ?? 'All');
        $instructor = $exam['creator_name'] ?? 'Faculty';
        
        $pdf->Cell(25, 5, 'Department:', 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42); $pdf->Cell(70, 5, $dept, 0, 0, 'L');
        
        $pdf->SetTextColor(100, 116, 139); $pdf->Cell(25, 5, 'Target Unit(s):', 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42); $pdf->Cell(70, 5, $units, 0, 1, 'L');

        $pdf->SetTextColor(100, 116, 139); $pdf->Cell(25, 5, 'Instructor:', 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42); $pdf->Cell(70, 5, $instructor, 0, 0, 'L');

        $pdf->SetTextColor(100, 116, 139); $pdf->Cell(25, 5, 'Parameters:', 0, 0, 'L');
        $pdf->SetTextColor(15, 23, 42); 
        $pdf->Cell(70, 5, ($exam['duration_minutes'] ?? 0) . ' Mins | Max Marks: ' . ($exam['total_marks'] ?? 0), 0, 1, 'L');

        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'MULTIPLE CHOICE QUESTIONS', 0, 1, 'C');
        $pdf->Ln(4);

        $qNum = 1;
        foreach ($questions as $q) {
            if ($pdf->GetY() > 250) { $pdf->AddPage(); }

            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(15, 23, 42);
            $pdf->MultiCell(190, 6, $qNum . ". " . str_replace("\r", "", $q['question_text']), 0, 'L');
            
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(71, 85, 105); 
            
            $options = ['A' => $q['option_a'] ?? '', 'B' => $q['option_b'] ?? '', 'C' => $q['option_c'] ?? '', 'D' => $q['option_d'] ?? ''];
            foreach ($options as $letter => $text) {
                if (!empty(trim($text))) {
                    $pdf->SetX(18); // Indent
                    $pdf->MultiCell(182, 5, "($letter)  $text", 0, 'L');
                }
            }
            $pdf->Ln(6);
            $qNum++;
        }

        if (ob_get_length()) ob_end_clean();
        if ($mode === 'S') return (string) $pdf->Output('S');
        $pdf->Output($mode, 'Offline_Exam_Paper.pdf');
        exit;
    }

    /**
     * 3. Generate Student Detailed Answer Sheet (Student Review Page)
     */
    public static function generateDetailedAnswerSheetPdf(array $student, array $exam, array $qaData, string $mode = 'I'): string 
    {
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->examTitle = (string)($exam['title'] ?? 'Detailed Answer Sheet');
        $pdf->footerSubtext = 'Student Review & Feedback Report';
        $pdf->metaInfo = "Candidate: " . ($student['name'] ?? 'Unknown') . "  |  Roll Number: " . ($student['roll_number'] ?? '-');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 10, 'Question-by-Question Review', 0, 1, 'L');
        $pdf->Ln(2);

        $qNum = 1;
        foreach ($qaData as $qa) {
            if ($pdf->GetY() > 250) { $pdf->AddPage(); }

            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->MultiCell(190, 6, "Q" . $qNum . ". " . str_replace("\r", "", $qa['question_text'] ?? ''), 0, 'L');

            $selected = $qa['selected_option'] ?? null;
            $correct = $qa['correct_option'] ?? '';
            $isCorrect = (bool)($qa['is_correct'] ?? false);

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 116, 139);
            $pdf->Cell(32, 6, "Your Answer: ", 0, 0, 'L');
            
            if (!$selected) {
                $pdf->SetTextColor(148, 163, 184); 
                $pdf->MultiCell(158, 6, 'Skipped / Unanswered', 0, 'L');
            } else {
                $pdf->SetTextColor($isCorrect ? 34 : 239, $isCorrect ? 197 : 68, $isCorrect ? 94 : 68); // Green if right, Red if wrong
                $selectedText = $qa['option_' . strtolower($selected)] ?? '';
                $pdf->MultiCell(158, 6, "[$selected] " . str_replace("\r", "", $selectedText), 0, 'L');
            }

            if (!$isCorrect) {
                $pdf->SetTextColor(100, 116, 139);
                $pdf->Cell(32, 6, "Correct Answer: ", 0, 0, 'L');
                $pdf->SetTextColor(34, 197, 94); // Always Green
                $correctText = $qa['option_' . strtolower($correct)] ?? '';
                $pdf->MultiCell(158, 6, "[$correct] " . str_replace("\r", "", $correctText), 0, 'L');
            }

            $pdf->Ln(2);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(3);
            $qNum++;
        }

        if (ob_get_length()) ob_end_clean();
        if ($mode === 'S') return (string) $pdf->Output('S');
        $pdf->Output($mode, 'Detailed_Answers_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($student['roll_number'] ?? 'Student')) . '.pdf');
        exit;
    }

    /**
     * 4. Generate individual student examination scorecard PDF (Student Results Page)
     */
    public static function generateStudentScorecardPdf(array $student, array $exam, array $attempt, array $stats, string $mode = 'I'): string 
    {
        $pdf = new ExamifyScorecardPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        $pdf->SetFillColor(30, 41, 59);
        $pdf->Rect(0, 0, 210, 28, 'F');

        $pdf->SetY(6);
        $pdf->SetFont('Helvetica', 'B', 15);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 7, 'EXAMIFY - EXAMINATION SCORECARD', 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(203, 213, 225);
        $pdf->Cell(0, 5, 'Official Student Assessment Grade Sheet', 0, 1, 'C');

        $pdf->SetTextColor(30, 41, 59);
        $metaBoxY = 34;
        $pdf->SetY($metaBoxY);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Rect(10, $metaBoxY, 190, 44, 'DF');

        $pdf->SetXY(15, $metaBoxY + 4);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'EXAMINATION:', 0, 0, 'L');

        $examTitle = (string)($exam['title'] ?? 'Examination');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $titleDisplay = $examTitle;
        while ($pdf->GetStringWidth($titleDisplay) > 150 && mb_strlen($titleDisplay) > 10) { $titleDisplay = mb_substr($titleDisplay, 0, -1); }
        if ($titleDisplay !== $examTitle) { $titleDisplay .= '...'; }
        $pdf->Cell(152, 6, $titleDisplay, 0, 1, 'L');

        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Line(15, $metaBoxY + 12, 195, $metaBoxY + 12);

        $gridY = $metaBoxY + 15;
        $pdf->SetXY(15, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Student Name:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->SetTextColor(15, 23, 42);
        $studentName = (string)($student['name'] ?? 'Candidate');
        while ($pdf->GetStringWidth($studentName) > 60 && mb_strlen($studentName) > 6) { $studentName = mb_substr($studentName, 0, -1); }
        $pdf->Cell(62, 6, $studentName, 0, 0, 'L');

        $pdf->SetXY(110, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Roll Number:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(57, 6, (string)($student['roll_number'] ?? '-'), 0, 1, 'L');

        $gridY += 8;
        $pdf->SetXY(15, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Department:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(15, 23, 42);
        $deptStr = (string)($student['department'] ?? 'General') . ' (Semester ' . (string)($student['semester'] ?? 1) . ')';
        $pdf->Cell(62, 6, $deptStr, 0, 0, 'L');

        $pdf->SetXY(110, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Date Submitted:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(15, 23, 42);
        $subStr = !empty($attempt['submitted_at']) ? date('d M Y, h:i A', strtotime($attempt['submitted_at'])) : 'N/A';
        $pdf->Cell(57, 6, $subStr, 0, 1, 'L');

        $gridY += 8;
        $pdf->SetXY(15, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Duration:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(62, 6, (string)($exam['duration_minutes'] ?? 0) . ' Minutes', 0, 0, 'L');

        $pdf->SetXY(110, $gridY);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(28, 6, 'Total Questions:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(57, 6, (string)($attempt['total_questions'] ?? 0) . ' Questions', 0, 1, 'L');

        $score = (float)($attempt['score'] ?? 0);
        $maxScore = (float)($exam['total_marks'] ?? 0);
        $pct = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
        $passed = ($pct >= 50);

        $bannerBg = $passed ? [240, 253, 244] : [254, 242, 242];
        $bannerBorder = $passed ? [34, 197, 94] : [239, 68, 68];
        $bannerText = $passed ? [22, 101, 52] : [153, 27, 27];

        $bannerY = $metaBoxY + 50;
        $pdf->SetY($bannerY);
        $pdf->SetFillColor($bannerBg[0], $bannerBg[1], $bannerBg[2]);
        $pdf->SetDrawColor($bannerBorder[0], $bannerBorder[1], $bannerBorder[2]);
        $pdf->Rect(10, $bannerY, 190, 32, 'DF');

        $pdf->SetXY(10, $bannerY + 3.5);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor($bannerText[0], $bannerText[1], $bannerText[2]);
        $pdf->Cell(190, 5, $passed ? 'RESULT: PASS / CLEARED' : 'RESULT: FAIL / NEEDS IMPROVEMENT', 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(190, 12, sprintf('%.2f', $score) . " / $maxScore Marks", 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(190, 5, "Overall Score Percentage: $pct%", 0, 1, 'C');

        $tableY = $bannerY + 42;
        $pdf->SetY($tableY);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(0, 7, 'Performance Breakdown', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(70, 8, 'Metric', 1, 0, 'L', true); $pdf->Cell(50, 8, 'Count', 1, 0, 'C', true); $pdf->Cell(70, 8, 'Proportion', 1, 1, 'C', true);

        $totalQs = (int)($attempt['total_questions'] ?? 0);
        $correct = (int)($stats['correct_count'] ?? 0);
        $wrong = (int)($stats['wrong_count'] ?? 0);
        $skipped = (int)($stats['skipped_count'] ?? 0);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(30, 41, 59);

        $metrics = [
            ['Correct Answers', $correct, ($totalQs > 0 ? round(($correct / $totalQs) * 100) : 0) . '%', [240, 253, 244]],
            ['Wrong / Incorrect Answers', $wrong, ($totalQs > 0 ? round(($wrong / $totalQs) * 100) : 0) . '%', [254, 242, 242]],
            ['Skipped / Unanswered', $skipped, ($totalQs > 0 ? round(($skipped / $totalQs) * 100) : 0) . '%', [248, 250, 252]],
            ['Total Questions Assigned', $totalQs, '100%', [241, 245, 249]]
        ];

        foreach ($metrics as $m) {
            $pdf->SetFillColor($m[3][0], $m[3][1], $m[3][2]);
            $pdf->Cell(70, 7, '  ' . $m[0], 1, 0, 'L', true); $pdf->Cell(50, 7, (string)$m[1], 1, 0, 'C', true); $pdf->Cell(70, 7, $m[2], 1, 1, 'C', true);
        }

        $sigY = max($pdf->GetY() + 18, 238);
        $pdf->SetDrawColor(148, 163, 184);
        $pdf->Line(20, $sigY + 12, 75, $sigY + 12); $pdf->Line(135, $sigY + 12, 190, $sigY + 12);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->SetXY(20, $sigY + 14); $pdf->Cell(55, 4, 'Candidate Signature', 0, 0, 'C');
        $pdf->SetXY(135, $sigY + 14); $pdf->Cell(55, 4, 'Controller of Examinations', 0, 0, 'C');

        if (ob_get_length()) ob_end_clean();
        if ($mode === 'S') return (string) $pdf->Output('S');
        $pdf->Output($mode, 'Scorecard_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($student['roll_number'] ?? 'Student')) . '.pdf');
        exit;
    }
}
