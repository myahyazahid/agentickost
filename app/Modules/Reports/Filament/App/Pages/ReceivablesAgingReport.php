<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Reports\Filament\App\ReportPage;
use App\Modules\Reports\Support\ReceivablesAging;
use App\Modules\Reports\Support\ReportAccess;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRow;
use App\Support\Reports\ReportSheet;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Receivables aging (FR-RPT-03): what each contract still owes, split by
 * how long it is overdue.
 */
class ReceivablesAgingReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Tagihan';

    protected static ?string $navigationLabel = 'Umur piutang';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'laporan/umur-piutang';

    protected static ?string $title = 'Umur piutang';

    public static function canAccess(): bool
    {
        return ReportAccess::receivables();
    }

    public function getSubheading(): string
    {
        return 'Sisa tagihan per kontrak hari ini, dikelompokkan menurut lama lewat jatuh tempo.';
    }

    protected static function columns(): array
    {
        return [
            'room' => ReportColumn::text('Kamar'),
            'payer' => ReportColumn::text('Pembayar'),
            ...array_map(fn (string $label): ReportColumn => ReportColumn::money($label), ReceivablesAging::BUCKETS),
            'total' => ReportColumn::money('Total'),
        ];
    }

    protected function filterComponents(): array
    {
        return [self::propertyField()];
    }

    protected function defaultFilters(): array
    {
        return ['property_id' => null];
    }

    public function sheet(): ReportSheet
    {
        $rows = ReceivablesAging::rows(User::current(), $this->filterPropertyId());
        $totals = ['room' => 'Total', 'payer' => null];

        foreach ([...array_keys(ReceivablesAging::BUCKETS), 'total'] as $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        return new ReportSheet(
            'Umur piutang',
            $this->propertyLabel().', per '.self::today()->translatedFormat('j F Y'),
            static::columns(),
            [...array_map(fn (array $row): ReportRow => ReportRow::line($row), $rows), ReportRow::total($totals)],
            'umur-piutang-'.self::today()->format('Ymd'),
            'Denda yang sudah terhitung ikut dijumlahkan.',
        );
    }
}
