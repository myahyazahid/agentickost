<?php

namespace App\Modules\Onboarding\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The downloadable import workbook (FR-ONB-02): a guide sheet with every
 * column explained and an example, then one empty sheet per kind of data
 * with the headers in the first row. Required columns end in "*".
 */
final class ImportTemplate
{
    public const FILENAME = 'template-impor-kostpilot.xlsx';

    public static function write(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $wrapped = (new Style)->setShouldWrapText();

        $guide = $writer->getCurrentSheet();
        $guide->setName('Petunjuk');
        $guide->setColumnWidth(26, 2);
        $guide->setColumnWidth(70, 3);
        $guide->setColumnWidth(22, 4);

        $writer->addRow(Row::fromValues(['Isi sheet Kamar, Penghuni, dan Kontrak mulai baris kedua. Sheet yang kosong dilewati. Kolom bertanda * wajib diisi.'], $bold));
        $writer->addRow(Row::fromValues(['Impor diproses per berkas: bila ada satu baris yang salah, tidak ada data yang disimpan. Perbaiki baris yang ditandai, lalu unggah lagi.'], $wrapped));
        $writer->addRow(Row::fromValues(['Tunggakan, deposit yang sedang dipegang, dan saldo kas dicatat terpisah di menu Saldo awal setelah impor.'], $wrapped));
        $writer->addRow(Row::fromValues([]));

        foreach (ImportSheet::cases() as $sheet) {
            $writer->addRow(Row::fromValues(["Sheet {$sheet->getLabel()}", 'Kolom', 'Keterangan', 'Contoh'], $bold));

            foreach ($sheet->columns() as $column) {
                $writer->addRow(Row::fromValues(['', $column->templateHeader(), $column->hint, $column->example], $wrapped));
            }

            $writer->addRow(Row::fromValues([]));
        }

        foreach (ImportSheet::cases() as $sheet) {
            $dataSheet = $writer->addNewSheetAndMakeItCurrent();
            $dataSheet->setName($sheet->getLabel());
            $dataSheet->setColumnWidthForRange(20, 1, max(1, count($sheet->columns())));

            $writer->addRow(Row::fromValues(
                array_map(fn (ImportColumn $column): string => $column->templateHeader(), $sheet->columns()),
                $bold,
            ));
        }

        $writer->close();
    }
}
