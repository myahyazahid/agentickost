<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Finance\Support\CashMovement;
use App\Modules\Finance\Support\FinancialStatements;
use App\Modules\Reports\Filament\App\ReportPage;
use App\Modules\Reports\Support\ReportAccess;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRow;
use App\Support\Reports\ReportSheet;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Money that actually came in and went out (FR-ACC-07), for owners who do
 * not read accrual reports. Same figures as the cash flow statement, in
 * plain words.
 */
class CashReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Uang masuk dan keluar';

    protected static ?int $navigationSort = 39;

    protected static ?string $slug = 'laporan/uang-masuk-keluar';

    protected static ?string $title = 'Uang masuk dan keluar';

    public static function canAccess(): bool
    {
        return ReportAccess::financialStatements();
    }

    public function getSubheading(): string
    {
        return 'Uang yang benar-benar diterima dan dibayarkan lewat kas, kas staf, dan rekening bank. Tagihan yang belum dibayar tidak dihitung.';
    }

    protected static function columns(): array
    {
        return ['label' => ReportColumn::text('Keterangan'), 'amount' => ReportColumn::money('Jumlah')];
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
        $cash = FinancialStatements::cashMovements($from, $to, $this->filterPropertyId());

        $in = array_values(array_filter($cash['movements'], fn (CashMovement $movement): bool => $movement->amount > 0));
        $out = array_values(array_filter($cash['movements'], fn (CashMovement $movement): bool => $movement->amount < 0));
        usort($in, fn (CashMovement $a, CashMovement $b): int => $b->amount <=> $a->amount);
        usort($out, fn (CashMovement $a, CashMovement $b): int => $a->amount <=> $b->amount);

        $totalIn = array_sum(array_map(fn (CashMovement $movement): int => $movement->amount, $in));
        $totalOut = -array_sum(array_map(fn (CashMovement $movement): int => $movement->amount, $out));

        $rows = [
            ReportRow::heading('label', 'Uang masuk'),
            ...array_map(fn (CashMovement $movement): ReportRow => ReportRow::line(['label' => $movement->label, 'amount' => $movement->amount]), $in),
            ReportRow::total(['label' => 'Total uang masuk', 'amount' => $totalIn]),
            ReportRow::heading('label', 'Uang keluar'),
            ...array_map(fn (CashMovement $movement): ReportRow => ReportRow::line(['label' => $movement->label, 'amount' => -$movement->amount]), $out),
            ReportRow::total(['label' => 'Total uang keluar', 'amount' => $totalOut]),
            ReportRow::total(['label' => $totalIn >= $totalOut ? 'Uang masuk lebih banyak' : 'Uang keluar lebih banyak', 'amount' => $totalIn - $totalOut]),
            ReportRow::line(['label' => 'Uang di kas dan bank awal periode', 'amount' => $cash['opening']]),
            ReportRow::line(['label' => 'Uang di kas dan bank akhir periode', 'amount' => $cash['closing']]),
        ];

        return new ReportSheet(
            'Uang masuk dan keluar',
            $this->propertyLabel().', '.$from->translatedFormat('j F Y').' sampai '.$to->translatedFormat('j F Y'),
            static::columns(),
            $rows,
            'uang-masuk-keluar-'.$from->format('Ymd').'-'.$to->format('Ymd'),
        );
    }
}
