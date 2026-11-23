<?php
declare(strict_types=1);

/**
 * Minimal dependency-free PDF writer (PDF 1.4).
 * It supports enough text, rectangles, and lines for a timetable sheet.
 */
class SimplePdf
{
    private array $content = [];
    private float $width = 842;
    private float $height = 595;

    public function text(float $x, float $y, string $text, float $size = 10, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $this->content[] = sprintf('BT %s %.1f Tf %.1f %.1f Td (%s) Tj ET', $font, $size, $x, $y, $escaped);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = .5): void
    {
        $this->content[] = sprintf('%.1f w %.1f %.1f m %.1f %.1f l S', $width, $x1, $y1, $x2, $y2);
    }

    public function rect(float $x, float $y, float $width, float $height, string $color = '1 1 1'): void
    {
        $this->content[] = sprintf('%s rg %.1f %.1f %.1f %.1f re f', $color, $x, $y, $width, $height);
        $this->line($x, $y, $x + $width, $y);
        $this->line($x, $y + $height, $x + $width, $y + $height);
        $this->line($x, $y, $x, $y + $height);
        $this->line($x + $width, $y, $x + $width, $y + $height);
    }

    public function cell(float $x, float $y, float $width, float $height, string $text, bool $header = false): void
    {
        $color = $header ? '0.93 0.95 1' : '1 1 1';
        $this->rect($x, $y, $width, $height, $color);
        $lines = $this->wrap($text, (int)max(12, ($width - 8) / 4.6));
        $startY = $y + $height - 13;
        foreach ($lines as $index => $line) {
            if ($index > 2) break;
            $this->text($x + 3, $startY - ($index * 10), $line, $header ? 7.5 : 6.8, $header);
        }
    }

    private function wrap(string $text, int $characters): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if (strlen($line . ' ' . $word) > $characters) {
                if ($line !== '') $lines[] = $line;
                $line = $word;
            } else {
                $line = trim($line . ' ' . $word);
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: [''];
    }

    public function output(string $filename): void
    {
        $stream = implode("\n", $this->content);
        $pageId = 3;
        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.1f %.1f] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>", $this->width, $this->height),
            4 => "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
            6 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $pdf;
        exit;
    }
}
