<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Finance\Enums\CashActivity;
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
 * Cash flow statement, direct method (FR-ACC-06): money through the cash,
 * staff cash, and bank accounts, by activity.
 */
class CashFlow extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Arus kas';

    protected static ?int $navigationSort = 42;

    protected static ?string $slug = 'laporan/arus-kas';

    protected static ?string $title = 'Laporan arus kas';

    public static function canAccess(): bool
    {
        return ReportAccess::financialStatements();
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
        $rows = [];
        $change = 0;

        foreach (CashActivity::cases() as $activity) {
            $movements = array_values(array_filter($cash['movements'], fn (CashMovement $movement): bool => $movement->activity === $activity));

            if ($movements === []) {
                continue;
            }

            $subtotal = array_sum(array_map(fn (CashMovement $movement): int => $movement->amount, $movements));
            $change += $subtotal;

            $rows[] = ReportRow::heading('label', $activity->getLabel());

            foreach ($movements as $movement) {
                $rows[] = ReportRow::line(['label' => $movement->label, 'amount' => $movement->amount]);
            }

            $rows[] = ReportRow::total(['label' => 'Kas bersih dari '.mb_strtolower(str_replace('Arus kas dari ', '', $activity->getLabel())), 'amount' => $subtotal]);
        }

        $rows[] = ReportRow::total(['label' => $change < 0 ? 'Penurunan kas' : 'Kenaikan kas', 'amount' => $change]);
        $rows[] = ReportRow::line(['label' => 'Kas dan bank awal periode', 'amount' => $cash['opening']]);

        $unexplained = $cash['closing'] - $cash['opening'] - $change;

        if ($unexplained !== 0) {
            $rows[] = ReportRow::line(['label' => 'Selisih karena jurnal antarproperti', 'amount' => $unexplained]);
        }

        $rows[] = ReportRow::total(['label' => 'Kas dan bank akhir periode', 'amount' => $cash['closing']]);

        return new ReportSheet(
            'Laporan arus kas',
            $this->propertyLabel().', '.$from->translatedFormat('j F Y').' sampai '.$to->translatedFormat('j F Y'),
            static::columns(),
            $rows,
            'arus-kas-'.$from->format('Ymd').'-'.$to->format('Ymd'),
        );
    }
}
