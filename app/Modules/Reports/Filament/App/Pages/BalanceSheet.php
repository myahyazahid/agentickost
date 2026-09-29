<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Finance\Support\FinancialStatements;
use App\Modules\Finance\Support\StatementLine;
use App\Modules\Reports\Filament\App\ReportPage;
use App\Modules\Reports\Support\ReportAccess;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRow;
use App\Support\Reports\ReportSheet;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * What the business owns and owes at the end of a day (FR-ACC-06).
 */
class BalanceSheet extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Neraca';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'laporan/neraca';

    protected static ?string $title = 'Neraca';

    public static function canAccess(): bool
    {
        return ReportAccess::financialStatements();
    }

    protected static function columns(): array
    {
        return ['label' => ReportColumn::text('Akun'), 'amount' => ReportColumn::money('Saldo')];
    }

    protected function filterComponents(): array
    {
        return [self::propertyField(), self::dateField('as_of', 'Per tanggal')];
    }

    protected function defaultFilters(): array
    {
        return ['property_id' => null, 'as_of' => self::today()->toDateString()];
    }

    public function sheet(): ReportSheet
    {
        $asOf = $this->filterDate('as_of');
        $statement = FinancialStatements::balanceSheet($asOf, $this->filterPropertyId());
        $sum = fn (array $lines): int => array_sum(array_map(fn (StatementLine $line): int => $line->amount, $lines));
        $line = fn (StatementLine $line): ReportRow => ReportRow::line(['label' => "{$line->code} {$line->label}", 'amount' => $line->amount]);

        $assets = $sum($statement['asset']);
        $liabilities = $sum($statement['liability']);
        $equity = $sum($statement['equity']) + $statement['earnings'];
        $difference = $assets - $liabilities - $equity;

        $rows = [
            ReportRow::heading('label', 'Aset'),
            ...array_map($line, $statement['asset']),
            ReportRow::total(['label' => 'Total aset', 'amount' => $assets]),
            ReportRow::heading('label', 'Kewajiban'),
            ...array_map($line, $statement['liability']),
            ReportRow::total(['label' => 'Total kewajiban', 'amount' => $liabilities]),
            ReportRow::heading('label', 'Ekuitas'),
            ...array_map($line, $statement['equity']),
            ReportRow::line(['label' => 'Laba ditahan dan laba berjalan', 'amount' => $statement['earnings']]),
            ReportRow::total(['label' => 'Total ekuitas', 'amount' => $equity]),
            ReportRow::total(['label' => 'Total kewajiban dan ekuitas', 'amount' => $liabilities + $equity]),
        ];

        if ($difference !== 0) {
            $rows[] = ReportRow::line(['label' => 'Selisih karena jurnal tanpa properti', 'amount' => $difference]);
        }

        return new ReportSheet(
            'Neraca',
            $this->propertyLabel().', per '.$asOf->translatedFormat('j F Y'),
            static::columns(),
            $rows,
            'neraca-'.$asOf->format('Ymd'),
            $this->filterPropertyId() === null ? null : 'Neraca per properti hanya memuat baris jurnal properti itu. Saldo yang dicatat tanpa properti ada di neraca semua properti.',
        );
    }
}
