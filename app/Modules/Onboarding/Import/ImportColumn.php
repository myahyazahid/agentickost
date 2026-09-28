<?php

namespace App\Modules\Onboarding\Import;

/**
 * One column of an import sheet. Headers are matched loosely, so "No. HP",
 * "no hp", and "No HP*" all find the same column.
 */
final readonly class ImportColumn
{
    public function __construct(
        public string $key,
        public string $header,
        public bool $required,
        public string $hint,
        public string $example,
    ) {}

    public function templateHeader(): string
    {
        return $this->required ? "{$this->header}*" : $this->header;
    }

    public static function normalize(string $header): string
    {
        $header = preg_replace('/\(.*?\)/', '', mb_strtolower($header)) ?? '';

        return trim(preg_replace('/[^a-z0-9]+/', '_', $header) ?? '', '_');
    }
}
