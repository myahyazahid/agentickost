<?php

namespace App\Support\Reports;

/**
 * A report as rows and columns, the one shape every report is exported in
 * (FR-RPT-05). Pages build it from the same figures they show, so the Excel
 * and PDF files match the screen.
 */
final readonly class ReportSheet
{
    /**
     * @param  array<string, ReportColumn>  $columns  by cell key, in display order
     * @param  list<ReportRow>  $rows
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public array $columns,
        public array $rows,
        public string $filename,
        public ?string $note = null,
    ) {}
}
