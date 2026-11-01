<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Exports any report table as CSV, PDF or Word (PRD 10.4, 10.5).
 */
final class ReportExporter
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int>>  $rows
     */
    public function export(string $title, string $period, array $headings, array $rows, string $format): string
    {
        return match ($format) {
            'pdf' => Pdf::loadView('documents.report', compact('title', 'period', 'headings', 'rows'))->setPaper('a4')->output(),
            'docx' => $this->docx($title, $period, $headings, $rows),
            default => $this->csv($headings, $rows),
        };
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int>>  $rows
     */
    private function csv(array $headings, array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        if ($h === false) {
            return '';
        }
        fwrite($h, "\xEF\xBB\xBF"); // Excel-friendly UTF-8 (₦)
        fputcsv($h, $headings);
        foreach ($rows as $r) {
            fputcsv($h, array_map('strval', $r));
        }
        rewind($h);

        return (string) stream_get_contents($h);
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string|int>>  $rows
     */
    private function docx(string $title, string $period, array $headings, array $rows): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $s = $word->addSection();
        $s->addText('GEA Haulage and Logistics', ['bold' => true, 'color' => '172F66', 'size' => 14]);
        $s->addText($title, ['bold' => true, 'size' => 12]);
        $s->addText($period, ['color' => '5E6578']);
        $t = $s->addTable(['borderSize' => 4, 'borderColor' => 'D9DCE3', 'cellMargin' => 70]);
        $t->addRow();
        foreach ($headings as $h) {
            $t->addCell(2400, ['bgColor' => '172F66'])->addText($h, ['bold' => true, 'color' => 'FFFFFF']);
        }
        foreach ($rows as $r) {
            $t->addRow();
            foreach ($r as $v) {
                $t->addCell(2400)->addText((string) $v);
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'gea-rpt');
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
