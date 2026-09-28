<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Builds import files the way an owner fills the template.
 */
final class ImportScenario
{
    /**
     * @param  array<string, list<list<mixed>>>  $sheets  sheet name => rows, the first row being the headers
     */
    public static function workbook(array $sheets): string
    {
        $path = self::tempPath('xlsx');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $first = true;

        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $writer->close();

        return $path;
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    public static function csv(array $rows, string $delimiter = ';'): string
    {
        $path = self::tempPath('csv');
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = $delimiter;
        $writer = new CsvWriter($options);
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    private static function tempPath(string $extension): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'kostpilot-import-'.bin2hex(random_bytes(6)).'.'.$extension;
    }
}
