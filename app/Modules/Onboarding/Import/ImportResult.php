<?php

namespace App\Modules\Onboarding\Import;

/**
 * What an import did, or would do, row by row (FR-ONB-03). Kept as plain
 * arrays so a Filament page can hold it between requests.
 *
 * @phpstan-type Row array{number: int, summary: string, errors: list<string>}
 * @phpstan-type Sheet array{sheet: string, label: string, rows: list<Row>}
 */
final class ImportResult
{
    /**
     * @param  array<string, Sheet>  $sheets  keyed by ImportSheet value
     * @param  list<string>  $fileErrors  problems with the file itself
     */
    public function __construct(
        private array $sheets = [],
        private array $fileErrors = [],
        public bool $committed = false,
    ) {}

    /**
     * @param  list<string>  $errors
     */
    public function addRow(ImportSheet $sheet, int $number, string $summary, array $errors = []): void
    {
        $this->sheets[$sheet->value] ??= ['sheet' => $sheet->value, 'label' => $sheet->getLabel(), 'rows' => []];
        $this->sheets[$sheet->value]['rows'][] = ['number' => $number, 'summary' => $summary, 'errors' => $errors];
    }

    public function addFileError(string $message): void
    {
        $this->fileErrors[] = $message;
    }

    public function isClean(): bool
    {
        return $this->fileErrors === [] && $this->errorCount() === 0 && $this->rowCount() > 0;
    }

    public function rowCount(?ImportSheet $sheet = null): int
    {
        return array_sum(array_map(
            fn (array $entry): int => count($entry['rows']),
            $sheet === null ? $this->sheets : array_filter([$this->sheets[$sheet->value] ?? null]),
        ));
    }

    public function errorCount(): int
    {
        $count = 0;

        foreach ($this->sheets as $entry) {
            foreach ($entry['rows'] as $row) {
                $count += $row['errors'] === [] ? 0 : 1;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public function fileErrors(): array
    {
        return $this->fileErrors;
    }

    /**
     * @return list<Sheet>
     */
    public function sheets(): array
    {
        return array_values($this->sheets);
    }

    /**
     * @return array{sheets: array<string, Sheet>, fileErrors: list<string>, committed: bool}
     */
    public function toArray(): array
    {
        return ['sheets' => $this->sheets, 'fileErrors' => $this->fileErrors, 'committed' => $this->committed];
    }

    /**
     * @param  array{sheets: array<string, Sheet>, fileErrors: list<string>, committed: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['sheets'], $data['fileErrors'], $data['committed']);
    }
}
