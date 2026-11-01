<?php

declare(strict_types=1);

namespace App\Domain\Finance\Documents;

use App\Models\Finance\Invoice;
use App\Models\Finance\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use ZipArchive;

/**
 * PDF (DomPDF) and Word (PhpWord) versions of invoices and quotes, plus bulk ZIP export (PRD 10.4).
 * Both formats read from DocumentData, so totals are identical.
 */
final class DocumentRenderer
{
    public function __construct(private readonly DocumentData $data) {}

    public function filename(Invoice|Quote $doc, string $ext): string
    {
        $num = $doc instanceof Quote ? $doc->number : $doc->displayNumber();

        return Str::slug($num).'.'.$ext;
    }

    public function pdf(Invoice|Quote $doc): string
    {
        return Pdf::loadView('documents.document', ['d' => $this->data->for($doc)])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => [public_path()]])
            ->output();
    }

    public function docx(Invoice|Quote $doc): string
    {
        $d = $this->data->for($doc);
        $navy = '172F66';

        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $section = $word->addSection(['marginTop' => Converter::cmToTwip(1.5), 'marginBottom' => Converter::cmToTwip(1.5),
            'marginLeft' => Converter::cmToTwip(1.8), 'marginRight' => Converter::cmToTwip(1.8)]);

        $section->addFooter()->addText($d['footer'].' '.$d['company']['name'].' · RC '.$d['company']['rc'].' · '.$d['company']['address'], ['size' => 8, 'color' => '5E6578']);

        $head = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $head->addRow();
        $logoCell = $head->addCell(5000);
        if (is_file($d['company']['logo'])) {
            $logoCell->addImage($d['company']['logo'], ['height' => 50]);
        } else {
            $logoCell->addText($d['company']['name'], ['bold' => true, 'size' => 14, 'color' => $navy]);
        }
        $right = $head->addCell(5000);
        $right->addText(strtoupper($d['title']), ['bold' => true, 'size' => 16, 'color' => $navy], ['alignment' => Jc::END]);
        $right->addText($d['number'], ['size' => 12, 'color' => $navy], ['alignment' => Jc::END]);
        if ($d['is_void'] || $d['is_draft']) {
            $right->addText($d['is_void'] ? 'VOID' : 'DRAFT', ['bold' => true, 'color' => 'C8102E'], ['alignment' => Jc::END]);
        }

        $section->addTextBreak();
        $parties = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $parties->addRow();
        $from = $parties->addCell(5000);
        $from->addText('FROM', ['size' => 8, 'color' => '5E6578']);
        $from->addText($d['company']['name'], ['bold' => true]);
        $from->addText($d['company']['address']);
        $from->addText('RC '.$d['company']['rc'].' · '.$d['company']['phone'].' · '.$d['company']['email']);
        if ($d['company']['tin']) {
            $from->addText('TIN '.$d['company']['tin'].($d['company']['vat_number'] ? ' · VAT '.$d['company']['vat_number'] : ''));
        }
        $to = $parties->addCell(5000);
        $to->addText('BILL TO', ['size' => 8, 'color' => '5E6578']);
        $to->addText($d['customer']['name'], ['bold' => true]);
        foreach (array_filter([$d['customer']['address'], trim(($d['customer']['phone'] ?? '').' '.($d['customer']['email'] ?? '')), $d['customer']['tin'] ? 'TIN '.$d['customer']['tin'] : null]) as $line) {
            $to->addText((string) $line);
        }

        $section->addTextBreak();
        $section->addText("Issue date: {$d['issue_date']}    {$d['due_label']}: {$d['due_date']}".($d['balance'] ? "    Balance due: {$d['balance']}" : ''), ['bold' => true]);

        if ($d['trip']) {
            $section->addTextBreak();
            $section->addText('TRIP DETAILS', ['size' => 8, 'color' => '5E6578']);
            foreach ($d['trip'] as $k => $v) {
                $section->addText("{$k}: {$v}");
            }
        }

        $section->addTextBreak();
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'D9DCE3', 'cellMargin' => 80, 'width' => 100 * 50, 'unit' => 'pct']);
        $table->addRow();
        foreach (['Description' => 5200, 'Qty' => 900, 'Unit price' => 1900, 'Amount' => 2000] as $h => $w) {
            $table->addCell($w, ['bgColor' => $navy])->addText($h, ['bold' => true, 'color' => 'FFFFFF'], $h === 'Description' ? [] : ['alignment' => Jc::END]);
        }
        foreach ($d['lines'] as $l) {
            $table->addRow();
            $table->addCell(5200)->addText($l['description']);
            $table->addCell(900)->addText($l['quantity'], [], ['alignment' => Jc::END]);
            $table->addCell(1900)->addText($l['unit_price'], [], ['alignment' => Jc::END]);
            $table->addCell(2000)->addText($l['amount'], [], ['alignment' => Jc::END]);
        }

        $totals = array_filter([
            'Subtotal' => $d['subtotal'],
            'Discount' => $d['discount'] ? '-'.$d['discount'] : null,
            "VAT ({$d['vat_rate']})" => $d['vat'],
            'Total' => $d['total'],
            'Paid' => $d['paid'] ? '-'.$d['paid'] : null,
            'WHT deducted' => $d['wht'] ? '-'.$d['wht'] : null,
            'Credit notes' => $d['credited'] ? '-'.$d['credited'] : null,
            'Balance due' => $d['balance'],
        ]);
        $section->addTextBreak();
        $t = $section->addTable(['alignment' => Jc::END]);
        foreach ($totals as $label => $value) {
            $t->addRow();
            $bold = in_array($label, ['Total', 'Balance due'], true);
            $t->addCell(3000)->addText($label, ['bold' => $bold]);
            $t->addCell(2200)->addText((string) $value, ['bold' => $bold], ['alignment' => Jc::END]);
        }

        $section->addTextBreak();
        $section->addText('Amount in words: '.$d['words'], ['italic' => true]);
        if ($d['bank']) {
            $section->addTextBreak();
            $section->addText('PAY BY BANK TRANSFER', ['size' => 8, 'color' => '5E6578']);
            foreach ($d['bank'] as $k => $v) {
                $section->addText("{$k}: {$v}");
            }
        }
        foreach (['Notes' => $d['notes'], 'Terms' => $d['terms']] as $k => $v) {
            if (filled($v)) {
                $section->addTextBreak();
                $section->addText(strtoupper($k), ['size' => 8, 'color' => '5E6578']);
                $section->addText((string) $v);
            }
        }
        if ($d['einvoice']) {
            $section->addTextBreak();
            $section->addText('FIRS E-INVOICE', ['size' => 8, 'color' => '5E6578']);
            $section->addText('IRN: '.$d['einvoice']['irn'], ['bold' => true]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gea-docx');
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /**
     * @param  iterable<Invoice|Quote>  $docs
     */
    public function zip(iterable $docs, string $format): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'gea-zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($docs as $doc) {
            $zip->addFromString($this->filename($doc, $format), $format === 'docx' ? $this->docx($doc) : $this->pdf($doc));
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
