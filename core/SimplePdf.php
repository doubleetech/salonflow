<?php

/**
 * SimplePdf
 * A tiny, dependency-free PDF file writer. No external library, no Composer —
 * just the raw PDF file format written out by hand. This is only meant for
 * the one job SalonFlow needs: simple text and tables on A4 pages.
 *
 * Known limitation: uses the PDF standard "Helvetica" font, which only
 * supports basic Latin characters (no ₦ symbol, no accented letters beyond
 * Latin-1). Reports print amounts as "NGN 1,000.00" instead of "₦1,000.00"
 * because of this. Fine for this app's purpose; would need an embedded
 * font to lift that limitation.
 *
 * Coordinates passed into text()/line() are measured from the TOP-LEFT of
 * the page (like a normal document), not PDF's native bottom-left origin —
 * this class does that conversion internally so callers never have to
 * think in upside-down coordinates.
 */
class SimplePdf
{
    private int $pageWidth = 595;  // A4 in points
    private int $pageHeight = 842; // A4 in points

    /** @var string[] finished page content streams */
    private array $pages = [];

    /** @var string[] operators for the page currently being built */
    private array $currentPageLines = [];

    private bool $hasOpenPage = false;

    public function addPage(): void
    {
        $this->flushCurrentPage();
        $this->currentPageLines = [];
        $this->hasOpenPage = true;
    }

    private function flushCurrentPage(): void
    {
        if ($this->hasOpenPage) {
            $this->pages[] = implode("\n", $this->currentPageLines);
        }
    }

    /** Draws a line of text. $x/$yFromTop are in points from the page's top-left corner. */
    public function text(float $x, float $yFromTop, string $str, float $size = 10, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $y = $this->pageHeight - $yFromTop;
        $escaped = $this->escape($str);
        $this->currentPageLines[] = "BT {$font} {$size} Tf {$x} {$y} Td ({$escaped}) Tj ET";
    }

    /** Draws a straight line — used for header underlines / row separators. */
    public function line(float $x1, float $yFromTop, float $x2, ?float $yFromTop2 = null, float $width = 0.5): void
    {
        $yFromTop2 = $yFromTop2 ?? $yFromTop;
        $y1 = $this->pageHeight - $yFromTop;
        $y2 = $this->pageHeight - $yFromTop2;
        $this->currentPageLines[] = "{$width} w {$x1} {$y1} m {$x2} {$y2} l S";
    }

    public function pageHeight(): int
    {
        return $this->pageHeight;
    }

    public function pageWidth(): int
    {
        return $this->pageWidth;
    }

    /** Escapes the three characters that are special inside a PDF literal string. */
    private function escape(string $s): string
    {
        // Order matters: backslash must be escaped first, or we'd double-escape
        // the backslashes we just inserted for the parentheses.
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        return $s;
    }

    /** Builds the final PDF file as a raw byte string. */
    public function output(): string
    {
        $this->flushCurrentPage();
        $this->hasOpenPage = false;

        $pageCount = count($this->pages);
        if ($pageCount === 0) {
            // Always emit at least one (blank) page — a PDF with zero pages
            // is invalid and some viewers will refuse to open it.
            $this->pages[] = '';
            $pageCount = 1;
        }

        // Object numbering scheme (kept contiguous 1..N — required so the
        // xref table below doesn't have to deal with gaps):
        //   1 = Catalog
        //   2 = Pages (parent)
        //   3 = Font: Helvetica
        //   4 = Font: Helvetica-Bold
        //   5, 7, 9, ... = each page object
        //   6, 8, 10, ... = each page's content stream object
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObjNum = 5 + $i * 2;
            $contentObjNum = 6 + $i * 2;

            $objects[$pageObjNum] =
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] " .
                "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObjNum} 0 R >>";

            $stream = $this->pages[$i];
            $length = strlen($stream);
            $objects[$contentObjNum] = "<< /Length {$length} >>\nstream\n{$stream}\nendstream";
        }

        ksort($objects, SORT_NUMERIC);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $maxObjNum = max(array_keys($objects));
        $xrefOffset = strlen($pdf);

        $pdf .= "xref\n0 " . ($maxObjNum + 1) . "\n";
        $pdf .= "0000000000 65535 f \n"; // object 0 is always the free-list head, per spec

        for ($n = 1; $n <= $maxObjNum; $n++) {
            // Every entry MUST be exactly 20 bytes for a strictly valid xref table.
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObjNum + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    /** Sends the right headers and streams the PDF straight to the browser as a download. */
    public function streamDownload(string $filename): void
    {
        $data = $this->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
    }
}
