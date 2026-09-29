<?php

namespace App\Modules\Onboarding\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Reads an import file into rows keyed by column (FR-ONB-02). An .xlsx file
 * is read sheet by sheet by sheet name; a .csv file holds one sheet, whose
 * kind the owner picks. Row numbers match what the owner sees in Excel.
 *
 * @phpstan-type SheetRows array<int, array<string, mixed>>
 */
final class SpreadsheetReader
{
    /**
     * More rows than any kost has rooms or residents; a file beyond this is
     * refused rather than read into memory.
     */
    public const MAX_ROWS = 5000;

    /**
     * @return array{sheets: array<string, SheetRows>, errors: list<string>}
     */
    public static function read(string $path, string $fileName, ?ImportSheet $csvSheet = null): array
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return ['sheets' => [], 'errors' => ['Pakai berkas .xlsx dari template, atau .csv.']];
        }

        if ($extension === 'csv' && $csvSheet === null) {
            return ['sheets' => [], 'errors' => ['Pilih isi berkas CSV: kamar, penghuni, atau kontrak.']];
        }

        $reader = $extension === 'csv' ? self::csvReader($path) : self::xlsxReader();
        $sheets = [];
        $errors = [];

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $kind = $extension === 'csv' ? $csvSheet : ImportSheet::fromSheetName($sheet->getName());

                if ($kind === null) {
                    continue;
                }

                $rows = self::rows($sheet->getRowIterator(), $kind, $errors);

                if ($rows !== []) {
                    $sheets[$kind->value] = $rows;
                }
            }
        } catch (Throwable) {
            return ['sheets' => [], 'errors' => ['Berkas tidak bisa dibaca. Unduh template lagi, atau simpan ulang berkasnya dari Excel.']];
        } finally {
            $reader->close();
        }

        if ($sheets === [] && $errors === []) {
            $errors[] = 'Berkas belum berisi data. Isi sheet Kamar, Penghuni, atau Kontrak mulai baris kedua.';
        }

        return ['sheets' => $sheets, 'errors' => $errors];
    }

    /**
     * @param  iterable<Row>  $rows
     * @param  list<string>  $errors
     * @return SheetRows
     */
    private static function rows(iterable $rows, ImportSheet $kind, array &$errors): array
    {
        $columns = null;
        $data = [];
        $number = 0;

        foreach ($rows as $row) {
            $number++;
            $values = $row->toArray();

            if ($columns === null) {
                $columns = self::columns($values, $kind, $errors);

                if ($columns === []) {
                    return [];
                }

                continue;
            }

            $record = [];

            foreach ($columns as $index => $key) {
                $record[$key] = $values[$index] ?? null;
            }

            if (array_filter($record, fn (mixed $value): bool => Cell::text($value) !== null) !== []) {
                $data[$number] = $record;
            }

            if (count($data) > self::MAX_ROWS) {
                $errors[] = "Sheet {$kind->getLabel()} berisi lebih dari ".number_format(self::MAX_ROWS, 0, ',', '.').' baris. Pecah menjadi beberapa berkas.';

                return [];
            }
        }

        return $data;
    }

    /**
     * Maps header positions to column keys. A sheet missing a required
     * column is reported and skipped.
     *
     * @param  array<int, mixed>  $headers
     * @param  list<string>  $errors
     * @return array<int, string>
     */
    private static function columns(array $headers, ImportSheet $kind, array &$errors): array
    {
        $known = [];

        foreach ($kind->columns() as $column) {
            $known[$column->key] = $column;
            $known[ImportColumn::normalize($column->header)] = $column;
        }

        $columns = [];

        foreach ($headers as $index => $header) {
            $column = $known[ImportColumn::normalize(Cell::text($header) ?? '')] ?? null;

            if ($column !== null) {
                $columns[$index] = $column->key;
            }
        }

        $missing = array_filter($kind->columns(), fn (ImportColumn $column): bool => $column->required && ! in_array($column->key, $columns, true));

        if ($missing !== []) {
            $names = implode(', ', array_map(fn (ImportColumn $column): string => $column->header, $missing));
            $errors[] = "Sheet {$kind->getLabel()} tidak punya kolom {$names}. Pakai judul kolom dari template di baris pertama.";

            return [];
        }

        return $columns;
    }

    private static function xlsxReader(): XlsxReader
    {
        $options = new XlsxOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;

        return new XlsxReader($options);
    }

    /**
     * Excel in an Indonesian locale saves CSV with semicolons.
     */
    private static function csvReader(string $path): CsvReader
    {
        $firstLine = strtok((string) @file_get_contents($path, length: 4096), "\n") ?: '';
        $options = new CsvOptions;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $options->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        return new CsvReader($options);
    }
}
