<?php

/**
 * PDF Generation Service  (Black & White / print-optimised edition)
 * Powered by pure-PHP FPDF library (lib/fpdf/fpdf.php)
 *
 * Design rules
 *  - Monochrome only (black, greys, white) -> prints cleanly on any B&W printer.
 *  - No solid dark fills (saves toner); header rows use light grey + bold text.
 *  - Status is never conveyed by colour alone: always icon + word.
 *  - Icons use the built-in ZapfDingbats font (no extra font files needed).
 *  - Header shows ONLY the college name.
 *
 * Output modes (last argument of every generate*() method)
 *  'P' = Preview page (HTML viewer with Print / Download buttons)   <- default
 *  'I' = Inline in browser        'D' = Force download
 *  'S' = Return PDF as string
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

/*
 * =====================================================================
 *  Shared base class: encoding fix + icon helpers + shared header/footer
 * =====================================================================
 */
#[\AllowDynamicProperties]
abstract class ExamifyBasePdf extends FPDF
{
    public const INSTITUTE = 'BENGAL INSTITUTE OF SCIENCE & TECHNOLOGY';
    public const INSTITUTE_CODE = 'BIST';
    /* ---- ZapfDingbats icon codes ---- */
    public const I_TICK = 52;  // ✔
    public const I_CROSS = 56;  // ✘
    public const I_STAR = 72;  // ★
    public const I_SQUARE = 110;  // ■
    public const I_BOX = 111;  // ❏
    public const I_BOX2 = 113;  // ❑
    public const I_BOX3 = 112;  // ❐
    public const I_DOT = 108;  // ●
    public const I_RING = 109;  // ❍
    public const I_DIAMOND = 117;  // ◆
    public const I_TRI = 115;  // ▲
    public const I_PENCIL = 46;  // ✎
    public const I_HAND = 42;  // ☛

    /* ---- Monochrome palette helpers ---- */
    public function ink(): void
    {
        $this->SetTextColor(0, 0, 0);
    }

    public function muted(): void
    {
        $this->SetTextColor(85, 85, 85);
    }

    public function soft(): void
    {
        $this->SetTextColor(120, 120, 120);
    }

    /* ---- UTF-8 -> cp1252 so names like "Café" / "Chatterjee’s" don't break FPDF ---- */
    public static function enc($s): string
    {
        $s = (string) $s;
        if ($s === '' || !preg_match('/[\x80-\xFF]/', $s)) {
            return $s;
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            return $s;  // already single-byte
        }
        $c = false;
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        }
        if ($c === false || $c === '') {
            $c = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        }
        return $c === false ? $s : $c;
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        parent::Cell($w, $h, self::enc($txt), $border, $ln, $align, $fill, $link);
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        parent::MultiCell($w, $h, self::enc($txt), $border, $align, $fill);
    }

    public function GetStringWidth($s)
    {
        return parent::GetStringWidth(self::enc($s));
    }

    /**
     * Cell with a small icon placed in front of the text.
     * Cell size, border, fill and ln behaviour are identical to Cell().
     * If icon + text would not fit, the icon is skipped (no overflow).
     */
    public function IconCell(float $w, float $h, int $glyph, string $text, $border = 0, int $ln = 0, string $align = 'C', bool $fill = false): void
    {
        // Do what FPDF::Cell() does: break the page FIRST, then read x/y.
        // (Reading y before the break caused stray/blank pages.)
        if ($this->y + $h > $this->PageBreakTrigger && !$this->InFooter && $this->AcceptPageBreak()) {
            $keepX = $this->x;
            $this->AddPage($this->CurOrientation, $this->CurPageSize, $this->CurRotation);
            $this->x = $keepX;
        }

        $x = $this->GetX();
        $y = $this->GetY();
        $fam = $this->FontFamily;
        $sty = $this->FontStyle;
        $sz = $this->FontSizePt;
        $g = chr($glyph);

        $this->SetFont('ZapfDingbats', '', max(6.0, $sz - 1));
        $iw = $this->GetStringWidth($g) + 1.6;
        $this->SetFont($fam, $sty, $sz);
        $tw = $this->GetStringWidth($text);
        $group = $iw + $tw;

        if ($group > $w - 2 * $this->cMargin) {
            $this->Cell($w, $h, $text, $border, $ln, $align, $fill);
            return;
        }

        parent::Cell($w, $h, '', $border, 0, '', $fill);  // frame / fill only

        if ($align === 'L') {
            $start = $x + $this->cMargin;
        } elseif ($align === 'R') {
            $start = $x + $w - $this->cMargin - $group;
        } else {
            $start = $x + ($w - $group) / 2;
        }

        $this->SetXY($start, $y);
        $this->SetFont('ZapfDingbats', '', max(6.0, $sz - 1));
        parent::Cell($iw, $h, $g, 0, 0, 'L');
        $this->SetFont($fam, $sty, $sz);
        $this->Cell($tw + 1, $h, $text, 0, 0, 'L');

        if ($ln === 1) {
            $this->SetXY($this->lMargin, $y + $h);
        } elseif ($ln === 2) {
            $this->SetXY($x, $y + $h);
        } else {
            $this->SetXY($x + $w, $y);
        }
    }

    /**
     * Draws an icon in the left padding of the current position without moving anything.
     */
    public function MarginIcon(int $glyph, float $h = 6, float $iw = 3.6): void
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $fam = $this->FontFamily;
        $sty = $this->FontStyle;
        $sz = $this->FontSizePt;

        $this->SetFont('ZapfDingbats', '', $sz);
        $this->SetXY($x - $iw, $y);
        parent::Cell($iw, $h, chr($glyph), 0, 0, 'C');
        $this->SetFont($fam, $sty, $sz);
        $this->SetXY($x, $y);
    }

    /**
     * College-name-only header (black text, double rule underneath).
     */
    protected function drawInstituteHeader(): void
    {
        $this->SetY(8);
        $this->SetFont('Helvetica', 'B', 15);
        $this->ink();
        $this->Cell(0, 8, self::INSTITUTE, 0, 1, 'C');

        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.7);
        $this->Line(10, 19, $this->GetPageWidth() - 10, 19);
        $this->SetLineWidth(0.2);
        $this->Line(10, 20.4, $this->GetPageWidth() - 10, 20.4);
    }

    protected function drawFooter(string $subtext): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(150, 150, 150);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());

        $this->SetFont('Helvetica', 'I', 8);
        $this->soft();
        $this->Cell(95, 10, 'Generated: ' . date('d M Y, h:i A'), 0, 0, 'L');
        $this->Cell(95, 10, $subtext . '  |  Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
}

/*
 * =====================================================================
 *  Report-style PDF (results, question paper, answer sheet)
 * =====================================================================
 */
#[\AllowDynamicProperties]
class ExamifyPdf extends ExamifyBasePdf
{
    public string $examTitle = '';
    public string $metaInfo = '';
    public string $footerSubtext = 'Official Academic Assessment Record';
    public bool $hideHeader = false;
    public string $paperCode = '';

    public function Header(): void
    {
        if ($this->hideHeader) {
            return;
        }

        // Full header on PAGE 1 only (same behaviour as before)
        if ($this->PageNo() === 1) {
            $this->drawInstituteHeader();

            $this->ink();
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
                $this->muted();
                $this->Cell(0, 5, $this->metaInfo, 0, 1, 'L');
                $this->ink();
            }

            $this->Ln(2);

            // Header bottom separator
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
            $this->SetLineWidth(0.2);
            $this->Ln(4);
        }
    }

    public function Footer(): void
    {
        if ($this->paperCode === '') {
            $this->drawFooter($this->footerSubtext);
            return;
        }

        // Two-row footer: row 1 = generated/page info, row 2 = paper set code
        $this->SetY(-14.5);
        $this->SetDrawColor(150, 150, 150);
        $this->SetLineWidth(0.2);
        $this->Line(10, $this->GetY() - 0.5, $this->GetPageWidth() - 10, $this->GetY() - 0.5);

        $this->SetFont('Helvetica', 'I', 8);
        $this->soft();
        $this->Cell(95, 5, 'Generated: ' . date('d M Y, h:i A'), 0, 0, 'L');
        $this->Cell(95, 5, $this->footerSubtext . '  |  Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');

        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(190, 5, $this->paperCode, 0, 0, 'C');
    }
}

/*
 * =====================================================================
 *  Scorecard PDF
 * =====================================================================
 */
#[\AllowDynamicProperties]
class ExamifyScorecardPdf extends ExamifyBasePdf
{
    public function Header(): void
    {
        $this->drawInstituteHeader();
    }

    public function Footer(): void
    {
        $this->drawFooter('Official Student Assessment Grade Sheet');
    }
}

/*
 * =====================================================================
 *  Service
 * =====================================================================
 */
class PdfService
{
    /*
     * -----------------------------------------------------------------
     * Delivery: preview page / inline / download / string
     * -----------------------------------------------------------------
     */
    private static function deliver(FPDF $pdf, string $mode, string $filename): string
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($mode === 'S') {
            return (string) $pdf->Output('S');
        }

        if ($mode === 'P') {
            self::previewPage((string) $pdf->Output('S'), $filename);
            exit;
        }

        $pdf->Output($mode, $filename);  // 'I' or 'D'
        exit;
    }

    /**
     * Self-contained HTML viewer: shows the PDF in the browser and offers
     * Print / Download only when the user clicks. No extra routes required.
     */
    private static function previewPage(string $pdfBytes, string $filename): void
    {
        $title = htmlspecialchars(preg_replace('/\.pdf$/i', '', $filename), ENT_QUOTES, 'UTF-8');
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Preview - %%TITLE%%</title>
            <style>
              *{box-sizing:border-box}
              html,body{height:100%;margin:0;font-family:Helvetica,Arial,sans-serif;background:#e5e5e5;color:#000}
              .bar{display:flex;align-items:center;gap:10px;padding:10px 16px;background:#fff;border-bottom:2px solid #000}
              .bar h1{flex:1;margin:0;font-size:14px;letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
              .btn{display:inline-block;padding:7px 16px;border:1.5px solid #000;background:#fff;color:#000;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer;border-radius:3px}
              .btn.primary{background:#000;color:#fff}
              .btn:hover{opacity:.85}
              .frame{height:calc(100% - 55px)}
              iframe{width:100%;height:100%;border:0;background:#fff}
              .fallback{padding:30px;text-align:center;font-size:14px}
            </style>
            </head>
            <body>
              <div class="bar">
                <h1>&#9998; %%TITLE%%</h1>
                <a class="btn" id="open" target="_blank" rel="noopener">Open in new tab</a>
                <button class="btn" id="print" type="button">&#9113; Print</button>
                <a class="btn primary" id="dl">&#10515; Download PDF</a>
                <button class="btn" id="close" type="button">&#10005; Close</button>
              </div>
              <div class="frame"><iframe id="v" title="PDF preview"></iframe></div>
            <script>
            (function(){
              var b64 = "%%B64%%", name = %%NAME%%;
              var bin = atob(b64), n = bin.length, arr = new Uint8Array(n);
              for (var i = 0; i < n; i++) arr[i] = bin.charCodeAt(i);
              var url = URL.createObjectURL(new Blob([arr], {type: 'application/pdf'}));
              document.getElementById('v').src = url;
              var dl = document.getElementById('dl'); dl.href = url; dl.download = name;
              document.getElementById('open').href = url;
              document.getElementById('print').onclick = function(){
                var f = document.getElementById('v').contentWindow; f.focus(); f.print();
              };
              document.getElementById('close').onclick = function(){
                if (window.history.length > 1) history.back(); else window.close();
              };
            })();
            </script>
            </body>
            </html>
            HTML;
        $html = str_replace(
            ['%%TITLE%%', '%%NAME%%', '%%B64%%'],
            [$title, json_encode($filename), base64_encode($pdfBytes)],
            $html
        );

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;
    }

    private static function safe(string $s): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $s);
    }

    /*
     * -----------------------------------------------------------------
     * Shared signature block
     * -----------------------------------------------------------------
     */
    private static function signatureBlock(ExamifyBasePdf $pdf, float $sigY, string $left, string $right): void
    {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(20, $sigY + 12, 75, $sigY + 12);
        $pdf->Line(135, $sigY + 12, 190, $sigY + 12);
        $pdf->SetLineWidth(0.2);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetXY(20, $sigY + 14);
        $pdf->IconCell(55, 4, ExamifyBasePdf::I_PENCIL, $left, 0, 0, 'C');
        $pdf->SetXY(135, $sigY + 14);
        $pdf->IconCell(55, 4, ExamifyBasePdf::I_PENCIL, $right, 0, 0, 'C');
    }

    /*
     * =================================================================
     * 1. Exam results summary report (Admin View Results Page)
     * =================================================================
     */
    private static function resultsTableHeader(ExamifyPdf $pdf, array $w): void
    {
        $pdf->SetFillColor(222, 222, 222);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($w['rank'], 8, 'Rank', 1, 0, 'C', true);
        $pdf->Cell($w['roll'], 8, 'Roll Number', 1, 0, 'C', true);
        $pdf->Cell($w['name'], 8, 'Student Name', 1, 0, 'L', true);
        $pdf->Cell($w['score'], 8, 'Score', 1, 0, 'C', true);
        $pdf->Cell($w['pct'], 8, 'Percentage', 1, 0, 'C', true);
        $pdf->Cell($w['status'], 8, 'Result', 1, 0, 'C', true);
        $pdf->Cell($w['time'], 8, 'Submitted', 1, 1, 'C', true);
    }

    public static function generateExamResultsPdf(array $exam, array $allResults, string $mode = 'P'): string
    {
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->examTitle = (string) ($exam['title'] ?? 'Examination Results');
        $dept = (string) ($exam['department'] ?? 'General');
        $sem = (string) ($exam['semester'] ?? 'All');
        $author = (string) ($exam['creator_name'] ?? 'College Faculty');
        $maxMarks = (float) ($exam['total_marks'] ?? 0);
        $totalSubmissions = count($allResults);

        $pdf->metaInfo = "Department: $dept (Sem $sem)  |  Maximum Marks: $maxMarks  |  Total Candidates: $totalSubmissions  |  Instructor: $author";
        $pdf->AddPage();

        $passedCount = 0;
        $failedCount = 0;
        $highestScore = 0.0;
        $totalScoreSum = 0.0;

        foreach ($allResults as $res) {
            $s = (float) ($res['score'] ?? 0);
            $totalScoreSum += $s;
            if ($s > $highestScore) {
                $highestScore = $s;
            }
            $pct = ($maxMarks > 0) ? ($s / $maxMarks) * 100 : 0;
            if ($pct >= 50.0) {
                $passedCount++;
            } else {
                $failedCount++;
            }
        }

        $avgScore = ($totalSubmissions > 0) ? round($totalScoreSum / $totalSubmissions, 2) : 0.0;
        $passRate = ($totalSubmissions > 0) ? round(($passedCount / $totalSubmissions) * 100, 1) : 0;

        // ---- KPI strip ----
        $kpiStartY = $pdf->GetY();
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(10, $kpiStartY, 190, 20, 'DF');
        $pdf->SetLineWidth(0.2);

        $colW = 190 / 5;
        $pdf->SetDrawColor(150, 150, 150);
        for ($i = 1; $i < 5; $i++) {
            $pdf->Line(10 + $colW * $i, $kpiStartY + 3, 10 + $colW * $i, $kpiStartY + 17);
        }

        $kpiY = $kpiStartY + 2.5;
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->muted();
        $pdf->SetXY(10, $kpiY);
        $pdf->IconCell($colW, 4, ExamifyBasePdf::I_SQUARE, 'CANDIDATES', 0, 0, 'C');
        $pdf->IconCell($colW, 4, ExamifyBasePdf::I_TICK, 'PASSED (>=50%)', 0, 0, 'C');
        $pdf->IconCell($colW, 4, ExamifyBasePdf::I_CROSS, 'FAILED (<50%)', 0, 0, 'C');
        $pdf->IconCell($colW, 4, ExamifyBasePdf::I_STAR, 'HIGHEST SCORE', 0, 0, 'C');
        $pdf->IconCell($colW, 4, ExamifyBasePdf::I_TRI, 'CLASS AVERAGE', 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->ink();
        $pdf->SetX(10);
        $pdf->Cell($colW, 9, (string) $totalSubmissions, 0, 0, 'C');
        $pdf->Cell($colW, 9, "$passedCount ($passRate%)", 0, 0, 'C');
        $pdf->Cell($colW, 9, (string) $failedCount, 0, 0, 'C');
        $pdf->Cell($colW, 9, (string) $highestScore . " / $maxMarks", 0, 0, 'C');
        $pdf->Cell($colW, 9, (string) $avgScore, 0, 1, 'C');

        // ---- Table ----
        $pdf->SetY($kpiStartY + 26);
        $w = ['rank' => 14, 'roll' => 28, 'name' => 56, 'score' => 26, 'pct' => 20, 'status' => 18, 'time' => 28];
        self::resultsTableHeader($pdf, $w);

        $pdf->SetFont('Helvetica', '', 9);
        $rank = 1;

        if (empty($allResults)) {
            $pdf->muted();
            $pdf->Cell(190, 12, 'No completed submissions recorded for this examination.', 1, 1, 'C');
        } else {
            foreach ($allResults as $row) {
                if ($pdf->GetY() > 255) {
                    $pdf->AddPage();
                    $pdf->SetY($pdf->GetY() + 2);
                    self::resultsTableHeader($pdf, $w);
                    $pdf->SetFont('Helvetica', '', 9);
                }

                $fill = ($rank % 2 === 0);
                $pdf->SetFillColor(242, 242, 242);
                $pdf->SetDrawColor(60, 60, 60);
                $pdf->ink();
                $score = (float) ($row['score'] ?? 0);
                $pct = ($maxMarks > 0) ? round(($score / $maxMarks) * 100) : 0;
                $isPass = ($pct >= 50);

                $pdf->Cell($w['rank'], 7, '#' . $rank++, 1, 0, 'C', $fill);
                $pdf->Cell($w['roll'], 7, (string) ($row['roll_number'] ?? ''), 1, 0, 'C', $fill);

                $orig = (string) ($row['name'] ?? '');
                $nameStr = $orig;
                while ($pdf->GetStringWidth($nameStr) > ($w['name'] - 4) && mb_strlen($nameStr) > 4) {
                    $nameStr = mb_substr($nameStr, 0, -1);
                }
                if ($nameStr !== $orig) {
                    $nameStr .= '..';
                }

                $pdf->Cell($w['name'], 7, $nameStr, 1, 0, 'L', $fill);
                $pdf->Cell($w['score'], 7, sprintf('%.2f', $score) . " / $maxMarks", 1, 0, 'C', $fill);
                $pdf->Cell($w['pct'], 7, "$pct%", 1, 0, 'C', $fill);

                $pdf->SetFont('Helvetica', 'B', 9);
                if ($isPass) {
                    $pdf->IconCell($w['status'], 7, ExamifyBasePdf::I_TICK, 'PASS', 1, 0, 'C', $fill);
                } else {
                    $pdf->IconCell($w['status'], 7, ExamifyBasePdf::I_CROSS, 'FAIL', 1, 0, 'C', $fill);
                }

                $pdf->SetFont('Helvetica', '', 8);
                $pdf->Cell($w['time'], 7, !empty($row['submitted_at']) ? date('d M, h:i A', strtotime($row['submitted_at'])) : '-', 1, 1, 'C', $fill);
                $pdf->SetFont('Helvetica', '', 9);
            }
        }

        $sigY = max($pdf->GetY() + 16, 238);
        if ($sigY > 252) {
            $pdf->AddPage();
            $sigY = max($pdf->GetY() + 20, 65);
        }
        self::signatureBlock($pdf, $sigY, 'Signature of Course Instructor', 'Signature of Head of Department');

        return self::deliver($pdf, $mode, 'Exam_Result_' . self::safe((string) ($exam['title'] ?? 'Report')) . '.pdf');
    }

    /*
     * =================================================================
     * 2. Printable Offline Exam Paper (with Answer Key at the end)
     * =================================================================
     */

    /**
     * Counts how many lines $text needs when wrapped into a cell of width $w (mm),
     * using the CURRENT font of $pdf (set the font before calling).
     */
    private static function countWrappedLines($pdf, string $text, float $w): int
    {
        $maxW = $w - 2;  // 1mm cell margin on each side
        $lines = 0;
        foreach (explode("\n", $text) as $para) {
            $words = preg_split('/\s+/', trim($para));
            $cur = '';
            $paraLines = 1;
            foreach ($words as $word) {
                $try = ($cur === '') ? $word : $cur . ' ' . $word;
                if ($pdf->GetStringWidth($try) > $maxW && $cur !== '') {
                    $paraLines++;
                    $cur = $word;
                } else {
                    $cur = $try;
                }
            }
            $lines += $paraLines;
        }
        return max(1, $lines);
    }

    public static function generateOfflineExamPaperPdf(array $exam, array $questions, string $mode = 'P'): string
    {
        global $pdo;
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->hideHeader = true;  // Use custom compact top-centered header
        $pdf->examTitle = (string) ($exam['title'] ?? 'Examination Paper');
        $pdf->footerSubtext = 'Official Offline Question Paper';

        $examId = (int) ($exam['id'] ?? 0);
        $setNo = '001'; // Default fallback

        if ($examId > 0 && $pdo) {
            // Ensure session is started for the debounce timer
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }

            $sessionKey = 'last_printed_exam_' . $examId;
            $now = time();

            // FPDF Double-Request Fix: If requested again within 5 seconds, DO NOT increment.
            if (isset($_SESSION[$sessionKey]) && ($now - $_SESSION[$sessionKey]) < 5) {
                
                // Just fetch the last generated set number
                $stmt = $pdo->prepare("SELECT MAX(set_number) FROM exam_set_logs WHERE exam_id = ?");
                $stmt->execute([$examId]);
                $lastSet = (int) $stmt->fetchColumn();
                $setNo = sprintf('%03d', max(1, $lastSet));
                
            } else {
                
                // Genuine new print request - Increment and save
                $stmt = $pdo->prepare("SELECT MAX(set_number) FROM exam_set_logs WHERE exam_id = ?");
                $stmt->execute([$examId]);
                $lastSet = (int) $stmt->fetchColumn();
                
                $nextSetNo = $lastSet + 1;
                $insert = $pdo->prepare("INSERT INTO exam_set_logs (exam_id, set_number) VALUES (?, ?)");
                $insert->execute([$examId, $nextSetNo]);
                
                // Start the 5-second cooldown
                $_SESSION[$sessionKey] = $now; 
                $setNo = sprintf('%03d', $nextSetNo);
                
            }
        }
        $pdf->paperCode = ExamifyBasePdf::INSTITUTE_CODE . '@' . date('d-m-Y') . '_MCQ#set-' . $setNo;

        $titleSlug = trim(preg_replace('/_+/', '_', self::safe((string) ($exam['title'] ?? 'Exam_Paper'))), '_');
        $fileName = ($titleSlug !== '' ? $titleSlug : 'Exam_Paper') . '-set-' . $setNo . '.pdf';
        $pdf->AliasNbPages();
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // ---------------- Full-Width Top Centered Header (Page 1) ----------------
        $totalQ = count($questions);
        $totalMarks = (int) ($exam['total_marks'] ?? $totalQ);
        $perQMarks = ($totalQ > 0) ? round($totalMarks / $totalQ, 2) : 1;
        $subjectStr = self::safe((string) ($exam['subject_name'] ?? $exam['subject'] ?? $exam['title'] ?? 'Examination Paper'));

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetY(10);

        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(190, 4.8, 'Bengal Institute of Science and Technology', 0, 1, 'C');

        $pdf->SetFont('Times', 'B', 10.5);
        $pdf->Cell(190, 4.2, $subjectStr, 0, 1, 'C');

        $pdf->SetFont('Times', 'B', 9.5);
        $pdf->Cell(190, 4.2, 'Full Marks ' . $totalMarks . ' | Set: ' . $setNo, 0, 1, 'C');

        $pdf->SetFont('Times', 'B', 9.5);
        $pdf->Cell(190, 4.2, 'A. Attempt all the MCQs ' . $perQMarks . 'x' . $totalQ . '=' . $totalMarks, 0, 1, 'C');

        // Horizontal divider line under top header
        $headerBottomY = $pdf->GetY() + 1.2;
        $pdf->SetLineWidth(0.3);
        $pdf->Line(10, $headerBottomY, 200, $headerBottomY);
        $pdf->SetLineWidth(0.2);

        // ---------------- 2-Column Layout Geometry ----------------
        $colStartY = $headerBottomY + 2.0;  // Starts right below the header on Page 1
        $pageBottom = 272.0;  // Strict 25mm bottom clearance so questions NEVER overlap the footer
        $colW = 91.0;  // 91mm width per column
        $colX = [10.0, 109.0];  // Left Col X = 10mm, Right Col X = 109mm
        $dividerX = 105.0;  // Center vertical divider line X = 105mm

        // Draw center vertical divider line on Page 1 (stops cleanly above footer)
        $pdf->Line($dividerX, $colStartY - 2.0, $dividerX, $pageBottom);

        $currentCol = 0;  // 0 = Left Column, 1 = Right Column
        $curY = $colStartY;

        // ---------------------------------------------------------
        // PART 1: Two-Column Student Question Paper (Unbroken Blocks)
        // ---------------------------------------------------------
        $qFontSize = 8.5;  // Compact 8.5pt font to fit 15+ questions per page
        $oFontSize = 8.5;
        $lineH = 3.6;  // Tight line height
        $qGap = 1.4;  // Gap between questions

        $advanceColumnOrPage = function () use ($pdf, &$currentCol, &$curY, &$colStartY, $pageBottom, $dividerX) {
            if ($currentCol === 0) {
                // Move from Left Column to Right Column on the same page
                $currentCol = 1;
                $curY = $colStartY;
            } else {
                // Move to Left Column of a brand new page
                $pdf->AddPage();
                $colStartY = 12.0;  // Start higher up on Page 2+
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.2);
                $pdf->Line($dividerX, $colStartY, $dividerX, $pageBottom);
                $currentCol = 0;
                $curY = $colStartY;
            }
        };

        $qNum = 1;
        foreach ($questions as $q) {
            // Collapse any internal line breaks or leading spaces
            $rawQ = (string) ($q['question_text'] ?? '');
            $cleanQ = preg_replace('/\s+/u', ' ', trim(self::safe($rawQ)));

            $opts = [];
            foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $key) {
                $rawOpt = (string) ($q[$key] ?? '');
                $cleanOpt = preg_replace('/\s+/u', ' ', trim(self::safe($rawOpt)));
                if ($cleanOpt !== '') {
                    $opts[] = ['letter' => $letter, 'text' => $cleanOpt];
                }
            }

            // 1. Measure Height Accurately (Number prefix '1.' + Question Text)
            $pdf->SetFont('Times', 'B', $qFontSize);
            $numPrefix = $qNum . '.';
            $numW = $pdf->GetStringWidth($numPrefix) + 3.0;  // Fixed width for number prefix (~6-8mm)
            $textW = $colW - $numW;

            $qLines = max(1, self::countWrappedLines($pdf, $cleanQ, $textW));
            $qH = $qLines * $lineH;

            // 2. Accurately Measure Options Height
            $pdf->SetFont('Times', '', $oFontSize);
            $nOpts = count($opts);
            $canFitTwoCols = ($nOpts > 0);
            $halfColW = $colW / 2.0;

            foreach ($opts as $opt) {
                $optStr = $opt['letter'] . ') ' . $opt['text'];
                if (($pdf->GetStringWidth($optStr) + 3.0) > $halfColW) {
                    $canFitTwoCols = false;
                    break;
                }
            }

            if ($canFitTwoCols) {
                $optTotalH = ceil($nOpts / 2) * $lineH;
            } else {
                $optTotalH = 0;
                foreach ($opts as $opt) {
                    $optStr = $opt['letter'] . ') ' . $opt['text'];
                    $optLines = max(1, self::countWrappedLines($pdf, $optStr, $colW - 4.0));
                    $optTotalH += $optLines * $lineH;
                }
            }

            // Total unbroken height of Question + ALL its Options
            $fullBlockH = $qH + $optTotalH + 0.5;

            // 3. Keep Question & Options Together Above Footer
            if (($curY + $fullBlockH > $pageBottom) && ($curY > $colStartY)) {
                $advanceColumnOrPage();
            }

            // 4. Render Question Stem: Number and Text locked on the exact same line
            $pdf->SetFont('Times', 'B', $qFontSize);
            $pdf->SetTextColor(0, 0, 0);

            // Print the number prefix on the left
            $pdf->SetXY($colX[$currentCol], $curY);
            $pdf->Cell($numW, $lineH, $numPrefix, 0, 0, 'L');

            // Print the question text right beside it (multiline if it wraps, aligning perfectly)
            $pdf->SetXY($colX[$currentCol] + $numW, $curY);
            $pdf->MultiCell($textW, $lineH, $cleanQ, 0, 'L');
            $curY = $pdf->GetY();

            // 5. Render All Options Together right below the Question (Letter + Text locked together)
            $pdf->SetFont('Times', '', $oFontSize);
            if ($canFitTwoCols) {
                for ($i = 0; $i < $nOpts; $i += 2) {
                    $pdf->SetXY($colX[$currentCol], $curY);
                    $pdf->Cell($halfColW, $lineH, $opts[$i]['letter'] . ') ' . $opts[$i]['text'], 0, 0, 'L');
                    if (isset($opts[$i + 1])) {
                        $pdf->Cell($halfColW, $lineH, $opts[$i + 1]['letter'] . ') ' . $opts[$i + 1]['text'], 0, 0, 'L');
                    }
                    $curY += $lineH;
                }
            } else {
                foreach ($opts as $opt) {
                    $optStr = $opt['letter'] . ') ' . $opt['text'];
                    $pdf->SetXY($colX[$currentCol], $curY);
                    $pdf->MultiCell($colW, $lineH, $optStr, 0, 'L');
                    $curY = $pdf->GetY();
                }
            }

            $curY += $qGap;
            $qNum++;
        }

        // ---------------------------------------------------------
        // PART 1.5: BIST OMR Sheet (Black & White, before Answer Key)
        // ---------------------------------------------------------
        $totalQuestions = count($questions);
        if ($totalQuestions > 0) {
            self::renderOmrSheetPage($pdf, $exam, $totalQuestions, $setNo);
        }

        // ---------------------------------------------------------
        // PART 2: Master Answer Key - ALL answers on ONE page
        // ---------------------------------------------------------
        $pdf->hideHeader = false;
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->ink();
        $pdf->IconCell(190, 9, ExamifyBasePdf::I_TICK, 'MASTER ANSWER KEY', 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->muted();
        $pdf->Cell(0, 5, 'Instructor Reference Only - Do Not Distribute', 0, 1, 'C');
        $pdf->Ln(5);

        $total = count($questions);
        $rowH = 6.5;
        $startY = $pdf->GetY();
        $maxRows = (int) floor(($pageBottom - $startY) / $rowH);
        $cols = max(5, (int) ceil($total / max(1, $maxRows)));
        $cols = min($cols, 8);
        $cellW = 190 / $cols;
        $rows = max(1, (int) ceil($total / $cols));

        $pdf->SetDrawColor(120, 120, 120);
        foreach ($questions as $idx => $q) {
            $col = intdiv($idx, $rows);
            $row = $idx % $rows;
            $x = 10 + ($col * $cellW);
            $y = $startY + ($row * $rowH);

            $correct = (string) ($q['correct_option'] ?? '');
            $tokens = array_filter(array_map('trim', explode(',', $correct)));
            $ans = implode(',', array_map('strtoupper', $tokens));

            $pdf->SetXY($x, $y);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Cell($cellW * 0.45, $rowH, 'Q' . ($idx + 1) . '.', 'B', 0, 'L');

            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($cellW * 0.55, $rowH, $ans, 'B', 0, 'L');
        }

        return self::deliver($pdf, $mode, $fileName);
    }

    /**
     * Draws a standard, easy-to-fill Black & White circular OMR bubble in FPDF.
     */
    private static function drawOmrBubble($pdf, float $cx, float $cy, float $r, string $label = '', float $fontSize = 7.5): void
    {
        $pdf->SetDrawColor(0, 0, 0);
        $steps = 28;
        $prevX = $cx + $r;
        $prevY = $cy;
        for ($i = 1; $i <= $steps; $i++) {
            $angle = $i * (2 * M_PI / $steps);
            $nx = $cx + $r * cos($angle);
            $ny = $cy + $r * sin($angle);
            $pdf->Line($prevX, $prevY, $nx, $ny);
            $prevX = $nx;
            $prevY = $ny;
        }
        if ($label !== '') {
            $pdf->SetFont('Helvetica', 'B', $fontSize);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($cx - $r, $cy - ($r * 0.68));
            $pdf->Cell($r * 2, $r * 1.36, $label, 0, 0, 'C');
        }
    }

    /**
     * Renders the standard full-page BIST Purulia OMR Sheet (Fixed 50 Questions, Blank Part-A, Left Logo).
     */
    private static function renderOmrSheetPage($pdf, array $exam, int $totalQuestions, string $setNo): void
    {
        $prevHideHeader = $pdf->hideHeader ?? false;
        $pdf->hideHeader = true;
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Enforce 100% Pure Black & White theme
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineWidth(0.3);

        // ---------------- OMR INSTITUTION HEADER WITH LEFT LOGO ----------------
        $logoCandidates = [
            __DIR__ . '/../../assets/images/college_logo_.png',
            __DIR__ . '/../assets/images/college_logo_.png',
            __DIR__ . '/college_logo_.png',
            ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/images/college_logo_.png',
        ];
        foreach ($logoCandidates as $logoPath) {
            if (is_file($logoPath)) {
                // Draw logo on the left side of the heading (X=11mm, Y=8mm, Width=19mm)
                $pdf->Image($logoPath, 11, 8, 19, 19);
                break;
            }
        }

        // Paper Set indicator on the top right
        $pdf->SetY(9);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->SetX(165);
        $pdf->Cell(35, 5, 'SET: ' . $setNo, 0, 0, 'R');

        // Main Heading shifted slightly right to balance the left logo
        $pdf->SetY(9.5);
        $pdf->SetFont('Times', 'B', 14);
        $pdf->SetX(30);
        $pdf->Cell(150, 6, 'BENGAL INSTITUTE OF SCIENCE AND TECHNOLOGY', 0, 1, 'C');

        $pdf->SetFont('Times', '', 10.5);
        $pdf->SetX(30);
        $pdf->Cell(150, 5, '(Affiliated to Sidho-Kanho-Birsha University, Purulia)', 0, 1, 'C');

        $pdf->SetFont('Times', 'B', 12);
        $pdf->SetX(30);
        $pdf->Cell(150, 6, 'OMR ANSWER SHEET', 0, 1, 'C');

        // ---------------- PART-A (ALL FIELDS BLANK FOR HANDWRITING) ----------------
        $pdf->SetY(28.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetX(10);
        $pdf->Cell(190, 5, 'PART-A', 0, 1, 'L');

        $partAY = $pdf->GetY();
        $partAH = 42.0;  // Generous height for comfortable student handwriting
        $pdf->SetLineWidth(0.45);
        $pdf->Rect(10, $partAY, 190, $partAH);
        $pdf->SetLineWidth(0.25);

        // Helper for drawing a label with a crisp dotted writing line
        $drawBlankField = function (float $x, float $y, string $label, float $labelW, float $lineW) use ($pdf) {
            $pdf->SetXY($x, $y);
            $pdf->SetFont('Times', 'B', 10.5);
            $pdf->Cell($labelW, 6, $label, 0, 0, 'L');

            $dotsCount = max(5, (int) floor($lineW / 1.55));
            $pdf->SetFont('Times', '', 9.5);
            $pdf->SetXY($x + $labelW, $y + 1.2);
            $pdf->Cell($lineW, 5, str_repeat('.', $dotsCount), 0, 0, 'L');
        };

        // Row 1: Name of the Examination (Blank) & Year (Blank)
        $drawBlankField(13, $partAY + 2.5, 'Name of the Examination', 43, 92);
        $drawBlankField(148, $partAY + 2.5, 'Year', 11, 38);

        // Row 2: Name of the Student (Blank)
        $drawBlankField(13, $partAY + 10.5, 'Name of the Student', 36, 148);

        // Row 3: Roll (Blank), Stream (Blank), Semester (Blank)
        $drawBlankField(13, $partAY + 18.5, 'Roll', 10, 50);
        $drawBlankField(76, $partAY + 18.5, 'Stream', 15, 42);
        $drawBlankField(136, $partAY + 18.5, 'Semester', 18, 43);

        // Row 4: Subject (Blank)
        $drawBlankField(13, $partAY + 26.5, 'Subject', 16, 168);

        // Row 5: Date of Examination (Blank)
        $drawBlankField(13, $partAY + 34.5, 'Date of Examination', 37, 147);

        // ---------------- PART-B (LARGER READABLE BUBBLES & LABELS) ----------------
        $pdf->SetY($partAY + $partAH + 2.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetX(10);
        $pdf->Cell(190, 5, 'PART-B', 0, 1, 'L');

        $partBY = $pdf->GetY();
        $boxH = 66.0;  // Expanded height for larger bubbles

        $drawDigitBlock = function (float $x, float $y, float $w, string $heading, int $cols, bool $hyphen = false) use ($pdf, $boxH) {
            $pdf->SetLineWidth(0.45);
            $pdf->Rect($x, $y, $w, $boxH);
            $pdf->SetLineWidth(0.25);

            // Header
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, 5.0, $heading, 'B', 0, 'C');

            $totalCols = $hyphen ? $cols + 1 : $cols;
            $half = intdiv($cols, 2);
            $cw = $w / $totalCols;

            // Write-in square boxes row
            $boxRowY = $y + 5.0;
            $boxRowH = 6.2;
            for ($c = 0; $c < $totalCols; $c++) {
                $cx = $x + ($c * $cw);
                $pdf->Rect($cx, $boxRowY, $cw, $boxRowH);
                if ($hyphen && $c === $half) {
                    $pdf->SetFont('Helvetica', 'B', 8.5);
                    $pdf->SetXY($cx, $boxRowY);
                    $pdf->Cell($cw, $boxRowH, '-', 0, 0, 'C');
                }
            }

            // 0 to 9 Bubble Rows (Larger radius 2.0mm, Font 7pt)
            $startBubbleY = $boxRowY + $boxRowH + 3.0;
            $rowStep = 5.35;
            for ($d = 0; $d <= 9; $d++) {
                $cy = $startBubbleY + ($d * $rowStep);
                for ($c = 0; $c < $totalCols; $c++) {
                    $cx = $x + ($c * $cw) + ($cw / 2);
                    if ($hyphen && $c === $half) {
                        $pdf->SetFont('Helvetica', 'B', 8);
                        $pdf->SetXY($cx - 2, $cy - 1.5);
                        $pdf->Cell(4, 3, '-', 0, 0, 'C');
                    } else {
                        self::drawOmrBubble($pdf, $cx, $cy, 2.0, (string) $d, 7.0);
                    }
                }
            }
        };

        // 1. ROLL (2 cols)
        $drawDigitBlock(10, $partBY, 14, 'ROLL', 2, false);

        // 2. REG. NO. (6 cols)
        $drawDigitBlock(25.5, $partBY, 39, 'REG. NO.', 6, false);

        // 3. YEAR (8 cols + hyphen)
        $drawDigitBlock(66, $partBY, 55, 'YEAR', 8, true);

        // 4. UG SEM (8 sems in 2 cols) & STREAM Block
        $ugX = 122.5;
        $ugW = 26.5;
        $pdf->SetLineWidth(0.45);
        $pdf->Rect($ugX, $partBY, $ugW, $boxH);
        $pdf->SetLineWidth(0.25);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetXY($ugX, $partBY);
        $pdf->Cell($ugW, 5.0, 'UG SEM', 'B', 0, 'C');

        $semItems = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];
        foreach ($semItems as $idx => $sLabel) {
            $colIdx = intdiv($idx, 4);
            $rowIdx = $idx % 4;

            $rx = $ugX + 1.2 + ($colIdx * 12.8);
            $ry = $partBY + 8.0 + ($rowIdx * 5.0);

            $pdf->SetFont('Times', 'B', 7.5);
            $pdf->SetXY($rx, $ry - 1.5);
            $pdf->Cell(7.5, 3, $sLabel, 0, 0, 'L');
            self::drawOmrBubble($pdf, $rx + 9.2, $ry, 1.75, '', 6.5);
        }

        // STREAM Section below semesters
        $streamHeaderY = $partBY + 27.5;
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetXY($ugX, $streamHeaderY);
        $pdf->Cell($ugW, 4.8, 'STREAM', 'TB', 0, 'C');

        // Main Streams (BBA, BCA)
        $main_streams = ['BBA', 'BCA'];
        foreach ($main_streams as $idx => $stLabel) {
            $ry = $streamHeaderY + 7.2 + ($idx * 5.0);
            $pdf->SetFont('Times', 'B', 7.5);
            $pdf->SetXY($ugX + 2.5, $ry - 1.5);
            $pdf->Cell(14, 3, $stLabel . '  -', 0, 0, 'L');
            self::drawOmrBubble($pdf, $ugX + $ugW - 5.0, $ry, 1.85, '', 6.5);
        }

        $lineY = $streamHeaderY + 15.2;
        $pdf->Line($ugX, $lineY, $ugX + $ugW, $lineY);

        $streams = ['AEC', 'SEC', 'MDC', 'VAC'];
        foreach ($streams as $idx => $stLabel) {
            $ry = $lineY + 3.5 + ($idx * 5.0);
            $pdf->SetFont('Times', 'B', 7.5);
            $pdf->SetXY($ugX + 2.5, $ry - 1.5);
            $pdf->Cell(14, 3, $stLabel . '  -', 0, 0, 'L');
            self::drawOmrBubble($pdf, $ugX + $ugW - 5.0, $ry, 1.85, '', 6.5);
        }

        // 5. EXAM YEAR (4 cols)
        $drawDigitBlock(150.5, $partBY, 27, 'EXAM YEAR', 4, false);

        // 6. Pure Black Timing Track Bars on Right Edge
        $pdf->SetFillColor(0, 0, 0);
        for ($b = 0; $b < 29; $b++) {
            $by = $partBY + ($b * 2.31);
            $pdf->Rect(181, $by, 9, 1.15, 'F');
        }
        $pdf->SetFillColor(255, 255, 255);

        // ---------------- PART-C (FIXED 50 QUESTIONS, STANDARD LARGE BUBBLES) ----------------
        $pdf->SetY($partBY + $boxH + 2.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetX(10);
        $pdf->Cell(190, 5, 'PART-C', 0, 1, 'L');

        $partCY = $pdf->GetY();
        $fixedQuestions = 50;  // Strictly fixed at 50 questions for every exam
        $cols = 5;
        $perCol = 10;  // 10 rows per column (1-10, 11-20, 21-30, 31-40, 41-50)

        $rowH = 9.0;  // Tall 9mm row height for effortless student shading
        $gridH = $perCol * $rowH;  // 90mm total height
        $colW = 190.0 / $cols;  // 38mm per column

        $pdf->SetLineWidth(0.45);
        $pdf->Rect(10, $partCY, 190, $gridH);
        $pdf->SetLineWidth(0.25);

        $bubbleRadius = 2.5;  // Standard 5.0mm diameter OMR answer bubble

        for ($c = 0; $c < $cols; $c++) {
            $cx = 10 + ($c * $colW);
            if ($c > 0) {
                $pdf->SetLineWidth(0.45);
                $pdf->Line($cx, $partCY, $cx, $partCY + $gridH);
                $pdf->SetLineWidth(0.25);
            }

            $numBoxW = 8.5;
            $pdf->Line($cx + $numBoxW, $partCY, $cx + $numBoxW, $partCY + $gridH);

            for ($r = 0; $r < $perCol; $r++) {
                $ry = $partCY + ($r * $rowH);
                if ($r > 0) {
                    $pdf->Line($cx, $ry, $cx + $colW, $ry);
                }

                $qNum = ($c * $perCol) + $r + 1;
                if ($qNum <= $fixedQuestions) {
                    $pdf->SetFont('Times', 'B', 10.5);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetXY($cx, $ry);
                    $pdf->Cell($numBoxW, $rowH, (string) $qNum, 0, 0, 'C');

                    $opts = ['A', 'B', 'C', 'D'];
                    $optSpace = ($colW - $numBoxW) / 4;
                    foreach ($opts as $oIdx => $letter) {
                        $bx = $cx + $numBoxW + ($oIdx * $optSpace) + ($optSpace / 2);
                        $by = $ry + ($rowH / 2);
                        self::drawOmrBubble($pdf, $bx, $by, $bubbleRadius, $letter, 8.0);
                    }
                }
            }
        }

        // ---------------- BOTTOM SIGNATURE BOXES ----------------
        $sigY = $partCY + $gridH + 3.5;
        $sigH = 15.0;

        $pdf->SetLineWidth(0.4);
        $pdf->Rect(10, $sigY, 88, $sigH);
        $pdf->Rect(112, $sigY, 88, $sigH);
        $pdf->SetLineWidth(0.25);

        $pdf->SetFont('Times', 'B', 8.5);
        $pdf->SetXY(10, $sigY + $sigH - 4.5);
        $pdf->Cell(88, 4, 'FULL SIGNATURE OF THE CANDIDATE', 0, 0, 'C');

        $pdf->SetXY(112, $sigY + $sigH - 4.5);
        $pdf->Cell(88, 4, 'SIGNATURE OF THE INVIGILATOR WITH DATE', 0, 0, 'C');

        // Restore header state for Part 2 (Master Answer Key)
        $pdf->hideHeader = $prevHideHeader;
    }

    /*
     * =================================================================
     * 3. Student Detailed Answer Sheet (Student Review Page)
     * =================================================================
     */
    public static function generateDetailedAnswerSheetPdf(array $student, array $exam, array $qaData, string $mode = 'P'): string
    {
        $pdf = new ExamifyPdf('P', 'mm', 'A4');
        $pdf->examTitle = (string) ($exam['title'] ?? 'Detailed Answer Sheet');
        $pdf->footerSubtext = 'Student Review & Feedback Report';
        $pdf->metaInfo = 'Candidate: ' . ($student['name'] ?? 'Unknown')
            . '  |  Roll Number: ' . ($student['roll_number'] ?? '-')
            . '| Department: ' . ($exam['department'] ?? '-')
            . '| Sem: ' . ($exam['semester'] ?? '-')
            . '|Subject: ' . ($exam['subject'] ?? '-');
        $pdf->AliasNbPages();
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->ink();
        $pdf->IconCell(190, 10, ExamifyBasePdf::I_BOX, 'Question-by-Question Review', 0, 1, 'L');
        $pdf->Ln(2);

        $qNum = 1;
        foreach ($qaData as $qa) {
            // ---- Estimate the height of this whole question block ----
            $qTxt = 'Q' . $qNum . '. ' . str_replace("\r", '', (string) ($qa['question_text'] ?? ''));
            $pdf->SetFont('Helvetica', 'B', 10);
            $blockH = self::countWrappedLines($pdf, $qTxt, 190) * 6;

            $pdf->SetFont('Helvetica', '', 9.5);
            foreach (['a', 'b', 'c', 'd'] as $k) {
                $ot = trim(str_replace("\r", '', (string) ($qa['option_' . $k] ?? '')));
                if ($ot !== '') {
                    $blockH += self::countWrappedLines($pdf, '(' . strtoupper($k) . ')  ' . $ot, 182) * 5;
                }
            }

            $optText = function ($csv) use ($qa): string {
                $parts = [];
                foreach (array_filter(array_map('trim', explode(',', (string) $csv))) as $tok) {
                    $parts[] = "[$tok] " . str_replace("\r", '', (string) ($qa['option_' . strtolower($tok)] ?? ''));
                }
                return implode('  |  ', $parts);
            };

            $pdf->SetFont('Helvetica', 'B', 10);
            $selH = !empty($qa['selected_option'])
                ? self::countWrappedLines($pdf, $optText($qa['selected_option']), 152) * 6
                : 6;
            $blockH += max(6, $selH);
            if (!(bool) ($qa['is_correct'] ?? false)) {
                $blockH += max(6, self::countWrappedLines($pdf, $optText($qa['correct_option'] ?? ''), 158) * 6);
            }
            $blockH += 5;  // trailing gap + separator line

            // New page only if the block does not fit here but WOULD fit on a fresh page.
            // (Y > 12 means we are not already at the top of a page -> no blank pages.)
            $usable = 297 - 15 - 10;
            if ($pdf->GetY() > 12 && $pdf->GetY() + $blockH > 297 - 15 && $blockH <= $usable) {
                $pdf->AddPage();
            }

            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->ink();
            $pdf->MultiCell(190, 6, 'Q' . $qNum . '. ' . str_replace("\r", '', (string) ($qa['question_text'] ?? '')), 0, 'L');

            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->SetTextColor(30, 30, 30);
            $options = ['A' => $qa['option_a'] ?? '', 'B' => $qa['option_b'] ?? '', 'C' => $qa['option_c'] ?? '', 'D' => $qa['option_d'] ?? ''];
            foreach ($options as $letter => $text) {
                if (trim((string) $text) !== '') {
                    $pdf->SetX(18);
                    $pdf->MultiCell(182, 5, "($letter)  $text", 0, 'L');
                }
            }

            $selected = $qa['selected_option'] ?? null;
            $correct = (string) ($qa['correct_option'] ?? '');
            $isCorrect = (bool) ($qa['is_correct'] ?? false);

            // ---- Your answer ----
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->muted();
            $pdf->IconCell(32, 6, ExamifyBasePdf::I_HAND, 'Your Answer:', 0, 0, 'L');

            if (!$selected) {
                $pdf->soft();
                $pdf->SetFont('Helvetica', 'I', 9);
                $pdf->IconCell(158, 6, ExamifyBasePdf::I_RING, 'Skipped / Unanswered', 0, 1, 'L');
            } else {
                $pdf->ink();
                $selTokens = array_filter(array_map('trim', explode(',', (string) $selected)));
                $selParts = [];
                foreach ($selTokens as $tok) {
                    $txt = $qa['option_' . strtolower($tok)] ?? '';
                    $selParts[] = "[$tok] " . str_replace("\r", '', (string) $txt);
                }

                // Tick for correct, cross for wrong (ZapfDingbats)
                $pdf->SetFont('ZapfDingbats', '', 10);
                $pdf->Cell(6, 6, chr($isCorrect ? ExamifyBasePdf::I_TICK : ExamifyBasePdf::I_CROSS), 0, 0, 'L');

                $pdf->SetFont('Helvetica', $isCorrect ? 'B' : '', 10);
                $pdf->MultiCell(152, 6, implode('  |  ', $selParts), 0, 'L');
            }

            // ---- Correct answer (only when wrong / skipped) ----
            if (!$isCorrect) {
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->muted();
                $pdf->IconCell(32, 6, ExamifyBasePdf::I_TICK, 'Correct Answer:', 0, 0, 'L');
                $pdf->ink();
                $pdf->SetFont('Helvetica', 'B', 9.5);
                $corrTokens = array_filter(array_map('trim', explode(',', $correct)));
                $corrParts = [];
                foreach ($corrTokens as $tok) {
                    $txt = $qa['option_' . strtolower($tok)] ?? '';
                    $corrParts[] = "[$tok] " . str_replace("\r", '', (string) $txt);
                }
                $pdf->MultiCell(158, 6, implode('  |  ', $corrParts), 0, 'L');
            }

            $pdf->Ln(2);
            $pdf->SetDrawColor(170, 170, 170);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(3);
            $qNum++;
        }

        return self::deliver($pdf, $mode, 'Detailed_Answers_' . self::safe((string) ($student['roll_number'] ?? 'Student')) . '.pdf');
    }

    /*
     * =================================================================
     * 4. Individual student scorecard (Student Results Page)
     * =================================================================
     */
    public static function generateStudentScorecardPdf(array $student, array $exam, array $attempt, array $stats, string $mode = 'P'): string
    {
        $pdf = new ExamifyScorecardPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        // ---------------- Meta box ----------------
        $pdf->ink();
        $metaBoxY = 34;
        $pdf->SetY($metaBoxY);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect(10, $metaBoxY, 190, 44, 'DF');
        $pdf->SetLineWidth(0.2);

        $label = function (float $x, float $y, int $icon, string $text) use ($pdf): void {
            $pdf->SetXY($x, $y);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->muted();
            $pdf->MarginIcon($icon);
            $pdf->Cell(28, 6, $text, 0, 0, 'L');
        };

        $label(15, $metaBoxY + 4, ExamifyBasePdf::I_BOX, 'EXAMINATION:');

        $examTitle = (string) ($exam['title'] ?? 'Examination') . '| Subject: ' . ($exam['subject'] ?? '-');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->ink();
        $titleDisplay = $examTitle;
        while ($pdf->GetStringWidth($titleDisplay) > 150 && mb_strlen($titleDisplay) > 10) {
            $titleDisplay = mb_substr($titleDisplay, 0, -1);
        }
        if ($titleDisplay !== $examTitle) {
            $titleDisplay .= '...';
        }
        $pdf->Cell(152, 6, $titleDisplay, 0, 1, 'L');

        $pdf->SetDrawColor(150, 150, 150);
        $pdf->Line(15, $metaBoxY + 12, 195, $metaBoxY + 12);

        // Row 1
        $gridY = $metaBoxY + 15;
        $label(15, $gridY, ExamifyBasePdf::I_DOT, 'Student Name:');
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->ink();
        $studentName = (string) ($student['name'] ?? 'Candidate');
        while ($pdf->GetStringWidth($studentName) > 60 && mb_strlen($studentName) > 6) {
            $studentName = mb_substr($studentName, 0, -1);
        }
        $pdf->Cell(62, 6, $studentName, 0, 0, 'L');

        $label(110, $gridY, ExamifyBasePdf::I_SQUARE, 'Roll Number:');
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->ink();
        $pdf->Cell(57, 6, (string) ($student['roll_number'] ?? '-'), 0, 1, 'L');

        // Row 2
        $gridY += 8;
        $label(15, $gridY, ExamifyBasePdf::I_DIAMOND, 'Department:');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->ink();
        $deptStr = (string) ($student['department'] ?? 'General') . ' (Semester ' . (string) ($student['semester'] ?? 1) . ')';
        $pdf->Cell(62, 6, $deptStr, 0, 0, 'L');

        $label(110, $gridY, ExamifyBasePdf::I_BOX2, 'Date Submitted:');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->ink();
        $subStr = !empty($attempt['submitted_at']) ? date('d M Y, h:i A', strtotime($attempt['submitted_at'])) : 'N/A';
        $pdf->Cell(57, 6, $subStr, 0, 1, 'L');

        // Row 3
        $gridY += 8;
        $label(15, $gridY, ExamifyBasePdf::I_RING, 'Duration:');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->ink();
        $pdf->Cell(62, 6, (string) ($exam['duration_minutes'] ?? 0) . ' Minutes', 0, 0, 'L');

        $label(110, $gridY, ExamifyBasePdf::I_BOX3, 'Total Questions:');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->ink();
        $pdf->Cell(57, 6, (string) ($attempt['total_questions'] ?? 0) . ' Questions', 0, 1, 'L');

        // ---------------- Result banner ----------------
        $score = (float) ($attempt['score'] ?? 0);
        $maxScore = (float) ($exam['total_marks'] ?? 0);
        $pct = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
        $passed = ($pct >= 50);

        $bannerY = $metaBoxY + 50;
        $pdf->SetY($bannerY);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth($passed ? 0.8 : 0.4);
        if ($passed) {
            $pdf->SetFillColor(255, 255, 255);
        } else {
            $pdf->SetFillColor(232, 232, 232);
        }
        $pdf->Rect(10, $bannerY, 190, 32, 'DF');
        if ($passed) {  // inner thin frame = "certified" look in B&W
            $pdf->SetLineWidth(0.2);
            $pdf->Rect(11.5, $bannerY + 1.5, 187, 29, 'D');
        }
        $pdf->SetLineWidth(0.2);

        $pdf->SetXY(10, $bannerY + 3.5);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->ink();
        $pdf->IconCell(
            190,
            5,
            $passed ? ExamifyBasePdf::I_TICK : ExamifyBasePdf::I_CROSS,
            $passed ? 'RESULT: PASS / CLEARED' : 'RESULT: FAIL / NEEDS IMPROVEMENT',
            0,
            1,
            'C'
        );

        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->Cell(190, 12, sprintf('%.2f', $score) . " / $maxScore Marks", 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->muted();
        $pdf->Cell(190, 5, "Overall Score Percentage: $pct%", 0, 1, 'C');

        // ---------------- Performance breakdown ----------------
        $tableY = $bannerY + 42;
        $pdf->SetY($tableY);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->ink();
        $pdf->IconCell(190, 7, ExamifyBasePdf::I_TRI, 'Performance Breakdown', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFillColor(222, 222, 222);
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(70, 8, 'Metric', 1, 0, 'L', true);
        $pdf->Cell(50, 8, 'Count', 1, 0, 'C', true);
        $pdf->Cell(70, 8, 'Proportion', 1, 1, 'C', true);

        $totalQs = (int) ($attempt['total_questions'] ?? 0);
        $correct = (int) ($stats['correct_count'] ?? 0);
        $wrong = (int) ($stats['wrong_count'] ?? 0);
        $skipped = (int) ($stats['skipped_count'] ?? 0);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->ink();

        $metrics = [
            [ExamifyBasePdf::I_TICK, 'Correct Answers', $correct, ($totalQs > 0 ? round(($correct / $totalQs) * 100) : 0) . '%', 255],
            [ExamifyBasePdf::I_CROSS, 'Wrong / Incorrect Answers', $wrong, ($totalQs > 0 ? round(($wrong / $totalQs) * 100) : 0) . '%', 238],
            [ExamifyBasePdf::I_RING, 'Skipped / Unanswered', $skipped, ($totalQs > 0 ? round(($skipped / $totalQs) * 100) : 0) . '%', 248],
            [ExamifyBasePdf::I_SQUARE, 'Total Questions Assigned', $totalQs, '100%', 228],
        ];

        foreach ($metrics as $m) {
            $pdf->SetFillColor($m[4], $m[4], $m[4]);
            $pdf->SetFont('Helvetica', $m[0] === ExamifyBasePdf::I_SQUARE ? 'B' : '', 9);
            $pdf->IconCell(70, 7, $m[0], $m[1], 1, 0, 'L', true);
            // IconCell with ln=0 moves to the next cell automatically
            $pdf->Cell(50, 7, (string) $m[2], 1, 0, 'C', true);
            $pdf->Cell(70, 7, $m[3], 1, 1, 'C', true);
        }

        $sigY = max($pdf->GetY() + 18, 238);
        self::signatureBlock($pdf, $sigY, 'Candidate Signature', 'Controller of Examinations');

        return self::deliver($pdf, $mode, 'Scorecard_' . self::safe((string) ($student['roll_number'] ?? 'Student')) . '.pdf');
    }
}
