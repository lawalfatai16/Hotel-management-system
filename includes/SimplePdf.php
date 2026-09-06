<?php
/**
 * SimplePdf: a minimal, dependency-free PDF generator for single-page
 * text documents (invoices, confirmation slips). No Composer package,
 * no external binary. Just the PDF spec's plainest possible structure:
 * one Catalog, one Pages, one Page, two base-14 fonts (Helvetica /
 * Helvetica-Bold, which every PDF reader has built in. No font
 * embedding needed), and one content stream.
 *
 * Coordinates are in PDF points, origin at bottom-left. To make this
 * easier to use from top-down invoice layouts, all public methods take
 * $y as "distance from the top of the page" and convert internally.
 */
class SimplePdf
{
    private float $pageWidth = 595.28;  // A4 width in points
    private float $pageHeight = 841.89; // A4 height in points
    private array $ops = []; // queued content-stream operations

    public function text(float $x, float $yFromTop, string $text, float $size = 10, bool $bold = false): void
    {
        $y = $this->pageHeight - $yFromTop;
        $font = $bold ? 'F2' : 'F1';
        $escaped = $this->escape($text);
        $this->ops[] = "BT /{$font} {$size} Tf 1 0 0 1 " . $this->num($x) . " " . $this->num($y) . " Tm ({$escaped}) Tj ET";
    }

    public function line(float $x1, float $yFromTop1, float $x2, float $yFromTop2, float $width = 0.75): void
    {
        $y1 = $this->pageHeight - $yFromTop1;
        $y2 = $this->pageHeight - $yFromTop2;
        $this->ops[] = $this->num($width) . " w " . $this->num($x1) . " " . $this->num($y1) . " m " . $this->num($x2) . " " . $this->num($y2) . " l S";
    }

    /** Render a simple table. $columns = [['label','width']], $rows = [[cell,cell,...]] */
    public function table(float $x, float $yFromTop, array $columns, array $rows, float $rowHeight = 18, float $fontSize = 9): float
    {
        $y = $yFromTop;
        $colX = $x;
        foreach ($columns as $col) {
            $this->text($colX, $y, $col[0], $fontSize, true);
            $colX += $col[1];
        }
        $y += 6;
        $this->line($x, $y, $x + array_sum(array_column($columns, 1)), $y);
        $y += $rowHeight - 6;

        foreach ($rows as $row) {
            $colX = $x;
            foreach ($row as $i => $cell) {
                $this->text($colX, $y, (string) $cell, $fontSize);
                $colX += $columns[$i][1] ?? 80;
            }
            $y += $rowHeight;
        }
        return $y; // returns the Y position after the table, so callers can continue below it
    }

    private function escape(string $text): string
    {
        // Base-14 Helvetica only supports WinAnsi/Latin-1, so transliterate
        // currency symbols like ₦ never produce a corrupt content stream.
        $text = str_replace('₦', 'NGN ', $text);
        $text = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Builds the final PDF byte string. */
    public function output(): string
    {
        $content = implode("\n", $this->ops);

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . $this->num($this->pageWidth) . " " . $this->num($this->pageHeight) . "] "
                    . "/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $objects[6] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefStart}\n%%EOF";

        return $pdf;
    }

    public function stream(string $filename): void
    {
        $pdf = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }
}
