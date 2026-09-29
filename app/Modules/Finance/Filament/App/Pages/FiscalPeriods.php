<?php

namespace App\Modules\Finance\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\CloseFiscalPeriod;
use App\Modules\Finance\Actions\ReopenFiscalPeriod;
use App\Modules\Finance\Enums\FinancePermission;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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
 * Closing the books month by month (FR-ACC-08, PRD §8.11). Shows the last
 * MONTHS months that have ended.
 */
class FiscalPeriods extends Page implements HasTable
{
    use InteractsWithTable;

    public const MONTHS = 24;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Keuangan';

    protected static ?string $navigationLabel = 'Tutup buku';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'tutup-buku';

    protected static ?string $title = 'Tutup buku';

    public static function canAccess(): bool
    {
        return User::current()->can('viewAny', FiscalPeriod::class);
    }

    public function getSubheading(): string
    {
        return 'Bulan yang ditutup menolak transaksi bertanggal di bulan itu, sehingga laporannya tidak berubah lagi. Hanya owner yang bisa membukanya kembali.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('label')->label('Bulan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (array $record): string => $record['is_open'] ? 'success' : 'gray'),
                TextColumn::make('closed_at')->label('Ditutup')->placeholder('-'),
                TextColumn::make('reopen_reason')
                    ->label('Dibuka kembali')
                    ->description(fn (array $record): ?string => $record['reopened_at'])
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->recordActions([$this->closeAction(), $this->reopenAction()])
            ->paginated(false);
    }

    private function closeAction(): Action
    {
        return Action::make('close')
            ->label('Tutup')
            ->icon(Heroicon::OutlinedLockClosed)
            ->visible(fn (array $record): bool => $record['is_open'] && User::current()->can('close', FiscalPeriod::class))
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => "Tutup buku {$record['label']}?")
            ->modalDescription('Setelah ditutup, tagihan, pembayaran, pengeluaran, dan jurnal bertanggal di bulan ini tidak bisa dicatat lagi.')
            ->modalSubmitActionLabel('Tutup buku')
            ->action(function (Action $action, array $record): void {
                DomainActions::forAction($action, fn () => app(CloseFiscalPeriod::class)->handle(['year' => $record['year'], 'month' => $record['month']]));

                Notification::make()->success()->title("Buku {$record['label']} ditutup")->send();
            });
    }

    private function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Buka kembali')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('danger')
            ->visible(fn (array $record): bool => ! $record['is_open'] && $record['period_id'] !== null && User::current()->can(FinancePermission::ReopenPeriods->value))
            ->modalHeading(fn (array $record): string => "Buka kembali {$record['label']}")
            ->modalDescription('Alasan tercatat di log audit. Laporan bulan ini bisa berubah setelah dibuka.')
            ->schema([
                Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000),
            ])
            ->action(function (Action $action, array $record, array $data): void {
                DomainActions::forAction($action, fn () => app(ReopenFiscalPeriod::class)->handle(FiscalPeriod::query()->whereKey($record['period_id'])->firstOrFail(), $data));

                Notification::make()->success()->title("Buku {$record['label']} dibuka kembali")->send();
            });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rows(): array
    {
        $tenant = app(TenantContext::class)->tenant();
        $timezone = $tenant->default_timezone;
        $lastEnded = CarbonImmutable::parse(CarbonImmutable::now($timezone)->toDateString())->startOfMonth()->subMonth();
        $periods = FiscalPeriod::query()->get()->keyBy(fn (FiscalPeriod $period): string => sprintf('%04d-%02d', $period->year, $period->month));
        $firstEntry = JournalEntry::query()->min('entry_date');
        $firstMonth = CarbonImmutable::parse(is_string($firstEntry) ? min($firstEntry, $tenant->created_at->toDateString()) : $tenant->created_at->toDateString())->startOfMonth();
        $rows = [];

        for ($month = $lastEnded, $i = 0; $i < self::MONTHS && $month->greaterThanOrEqualTo($firstMonth); $month = $month->subMonth(), $i++) {

            $key = $month->format('Y-m');
            $period = $periods->get($key);
            $isOpen = $period === null || $period->isOpen();

            $rows[$key] = [
                '__key' => $key,
                'period_id' => $period?->id,
                'year' => $month->year,
                'month' => $month->month,
                'label' => $month->translatedFormat('F Y'),
                'is_open' => $isOpen,
                'status' => $isOpen ? 'Terbuka' : 'Ditutup',
                'closed_at' => $isOpen ? null : $period->closed_at?->timezone($timezone)->translatedFormat('j M Y H:i'),
                'reopened_at' => $period?->reopened_at?->timezone($timezone)->translatedFormat('j M Y H:i'),
                'reopen_reason' => $period?->reopen_reason,
            ];
        }

        return $rows;
    }
}
