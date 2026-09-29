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
 * Revenue and expenses over a period, per property or for the whole
 * business (FR-ACC-06). Accrual basis: rent counts when billed.
 */
class ProfitAndLoss extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Laba rugi';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'laporan/laba-rugi';

    protected static ?string $title = 'Laporan laba rugi';

    public static function canAccess(): bool
    {
        return ReportAccess::financialStatements();
    }

    public function getSubheading(): string
    {
        return 'Pendapatan dihitung saat tagihan terbit, bukan saat dibayar. Untuk uang yang benar-benar masuk, buka laporan Uang masuk dan keluar.';
    }

    protected static function columns(): array
    {
        return ['label' => ReportColumn::text('Akun'), 'amount' => ReportColumn::money('Jumlah')];
    }

    protected function filterComponents(): array
    {
        return [self::propertyField(), self::dateField('from', 'Dari'), self::dateField('to', 'Sampai')];
    }

    protected function defaultFilters(): array
    {
        return ['property_id' => null, 'from' => self::today()->startOfMonth()->toDateString(), 'to' => self::today()->toDateString()];
    }

    public function sheet(): ReportSheet
    {
        $from = $this->filterDate('from');
        $to = $this->filterDate('to');
        $statement = FinancialStatements::profitAndLoss($from, $to, $this->filterPropertyId());

        $revenue = array_sum(array_map(fn (StatementLine $line): int => $line->amount, $statement['revenue']));
        $expense = array_sum(array_map(fn (StatementLine $line): int => $line->amount, $statement['expense']));
        $profit = $revenue - $expense;

        $rows = [
            ReportRow::heading('label', 'Pendapatan'),
            ...array_map(fn (StatementLine $line): ReportRow => ReportRow::line(['label' => "{$line->code} {$line->label}", 'amount' => $line->amount]), $statement['revenue']),
            ReportRow::total(['label' => 'Total pendapatan', 'amount' => $revenue]),
            ReportRow::heading('label', 'Beban'),
            ...array_map(fn (StatementLine $line): ReportRow => ReportRow::line(['label' => "{$line->code} {$line->label}", 'amount' => $line->amount]), $statement['expense']),
            ReportRow::total(['label' => 'Total beban', 'amount' => $expense]),
            ReportRow::total(['label' => $profit < 0 ? 'Rugi bersih' : 'Laba bersih', 'amount' => $profit]),
        ];

        return new ReportSheet(
            'Laporan laba rugi',
            $this->propertyLabel().', '.$from->translatedFormat('j F Y').' sampai '.$to->translatedFormat('j F Y'),
            static::columns(),
            $rows,
            'laba-rugi-'.$from->format('Ymd').'-'.$to->format('Ymd'),
        );
    }
}
