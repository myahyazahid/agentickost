<?php

namespace App\Support\Reports;

use App\Support\Money\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a report as Excel or PDF (FR-RPT-05). In Excel, money stays a
 * number so owners can add it up themselves.
 */
final class ReportExporter
{
    public function excel(ReportSheet $sheet): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheet): void {
            $writer = new Writer;
            $writer->openToFile('php://output');

            $bold = (new Style)->setFontBold();
            $money = (new Style)->setFormat('#,##0');
            $boldMoney = (new Style)->setFontBold()->setFormat('#,##0');

            $writer->getCurrentSheet()->setColumnWidth(40, 1);
            $writer->getCurrentSheet()->setColumnWidthForRange(18, 2, max(2, count($sheet->columns)));

            $writer->addRow(Row::fromValues([$sheet->title], $bold));
            $writer->addRow(Row::fromValues([$sheet->subtitle]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(array_values(array_map(fn (ReportColumn $column): string => $column->label, $sheet->columns)), $bold));

            foreach ($sheet->rows as $row) {
                $emphasis = $row->kind !== ReportRowKind::Line;
                $cells = [];

                foreach ($sheet->columns as $key => $column) {
                    $value = $row->cells[$key] ?? null;
                    $style = match (true) {
                        $column->isMoney && $emphasis => $boldMoney,
                        $column->isMoney => $money,
                        $emphasis => $bold,
                        default => null,
                    };
                    $cells[] = Cell::fromValue($value, $style);
                }

                $writer->addRow(new Row($cells));
            }

            if ($sheet->note !== null) {
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([$sheet->note]));
            }

            $writer->close();
        }, "{$sheet->filename}.xlsx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function pdf(ReportSheet $sheet): StreamedResponse
    {
        $pdf = Pdf::loadView('reports.pdf', [
            'sheet' => $sheet,
            'format' => fn (ReportColumn $column, string|int|null $value): string => match (true) {
                $value === null => '',
                $column->isMoney && is_int($value) => Rupiah::format($value),
                default => (string) $value,
            },
        ])->setPaper('a4', count($sheet->columns) > 4 ? 'landscape' : 'portrait');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, "{$sheet->filename}.pdf", ['Content-Type' => 'application/pdf']);
    }
}
