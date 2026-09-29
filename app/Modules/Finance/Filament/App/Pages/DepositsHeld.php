<?php

namespace App\Modules\Finance\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositsHeld as DepositsHeldQuery;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\ReportDownloads;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRow;
use App\Support\Reports\ReportSheet;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Deposit held per property (FR-DEP-04): money owed back to residents.
 */
class DepositsHeld extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Deposit dipegang';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'deposit';

    protected static ?string $title = 'Deposit dipegang';

    public static function canAccess(): bool
    {
        return User::current()->can('viewAny', DepositTransaction::class);
    }

    public function getSubheading(): string
    {
        return 'Deposit adalah uang penghuni yang harus dikembalikan saat keluar, kecuali dipotong dengan alasan.';
    }

    protected function getHeaderActions(): array
    {
        return [ReportDownloads::make(fn (): ReportSheet => $this->sheet())];
    }

    public function sheet(): ReportSheet
    {
        $properties = DepositsHeldQuery::perProperty(User::current())->orderBy('name')->get();
        $rows = $properties->map(fn (Property $property): ReportRow => ReportRow::line([
            'name' => $property->name,
            'contracts' => (string) $property->getAttribute('contracts_holding_count'),
            'amount' => (int) $property->getAttribute('deposit_held_amount'),
        ]))->values()->all();

        return new ReportSheet(
            'Deposit dipegang',
            'Per '.CarbonImmutable::now(app(TenantContext::class)->tenant()->default_timezone)->translatedFormat('j F Y'),
            ['name' => ReportColumn::text('Properti'), 'contracts' => ReportColumn::text('Kontrak dengan deposit'), 'amount' => ReportColumn::money('Deposit dipegang')],
            [...$rows, ReportRow::total(['name' => 'Total', 'amount' => array_sum(array_map(fn (ReportRow $row): int => (int) ($row->cells['amount'] ?? 0), $rows))])],
            'deposit-dipegang-'.CarbonImmutable::now(app(TenantContext::class)->tenant()->default_timezone)->format('Ymd'),
        );
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DepositsHeldQuery::perProperty(User::current()))
            ->columns([
                TextColumn::make('name')->label('Properti')->searchable(),
                TextColumn::make('contracts_holding_count')
                    ->label('Kontrak dengan deposit')
                    ->numeric(),
                MoneyColumn::make('deposit_held_amount')
                    ->label('Deposit dipegang')
                    ->weight('bold'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('Belum ada properti')
            ->emptyStateDescription('Deposit tercatat di sini saat bagian deposit di tagihan pertama dibayar.');
    }
}
