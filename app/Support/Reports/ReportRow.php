<?php

namespace App\Support\Reports;

/**
 * One row of a report. Money cells hold whole rupiah as int; a heading row
 * names a section and a total row closes one.
 */
final readonly class ReportRow
{
    /**
     * @param  array<string, string|int|null>  $cells  by column key
     */
    public function __construct(
        public array $cells,
        public ReportRowKind $kind = ReportRowKind::Line,
    ) {}

    /**
     * @param  array<string, string|int|null>  $cells
     */
    public static function line(array $cells): self
    {
        return new self($cells);
    }

    public static function heading(string $firstColumn, string $label): self
    {
        return new self([$firstColumn => $label], ReportRowKind::Heading);
    }

    /**
     * @param  array<string, string|int|null>  $cells
     */
    public static function total(array $cells): self
    {
        return new self($cells, ReportRowKind::Total);
    }
}
