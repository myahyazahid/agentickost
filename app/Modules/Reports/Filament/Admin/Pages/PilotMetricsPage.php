<?php

namespace App\Modules\Reports\Filament\Admin\Pages;

use App\Modules\Reports\Support\PilotMetrics;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * Pilot metrics per tenant for one month (PRD §15.1, roadmap M1.10): how
 * many rent invoices were paid by their due date, and how long payments
 * waited for verification. Reviewed weekly during the pilot.
 *
 * @property-read Schema $form
 */
class PilotMetricsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Metrik pilot';

    protected static ?string $slug = 'metrik-pilot';

    protected static ?string $title = 'Metrik pilot';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = Filament::auth()->user();

        return $admin !== null && $admin->can('viewAny', Tenant::class);
    }

    public function getSubheading(): string
    {
        return 'Ketepatan bayar: tagihan sewa yang jatuh tempo di bulan ini dan lunas paling lambat pada tanggal jatuh temponya. Waktu verifikasi: median lama pembayaran menunggu diverifikasi. Pembandingnya, kondisi sebelum memakai Agentic Kost, dicatat dari wawancara owner.';
    }

    public function mount(): void
    {
        $this->form->fill(['month' => now('Asia/Jakarta')->format('Y-m')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('month')
                    ->label('Bulan')
                    ->options(self::monthOptions())
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('form'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('name')->label('Tenant'),
                TextColumn::make('on_time')
                    ->label('Ketepatan bayar')
                    ->description(fn (array $record): string => "{$record['on_time_count']} dari {$record['due']} tagihan"),
                TextColumn::make('verification')
                    ->label('Waktu verifikasi (median)')
                    ->description(fn (array $record): string => "{$record['verified']} pembayaran lewat antrean"),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedPresentationChartLine)
            ->emptyStateHeading('Belum ada tenant aktif')
            ->emptyStateDescription('Metrik muncul setelah kost pilot mulai menerbitkan tagihan dan mencatat pembayaran.');
    }

    /**
     * @return array<string, array{name: string, on_time: string, on_time_count: int, due: int, verification: string, verified: int}>
     */
    private function rows(): array
    {
        $month = CarbonImmutable::createFromFormat('!Y-m', (string) ($this->data['month'] ?? now()->format('Y-m'))) ?: CarbonImmutable::now();
        $rows = [];

        foreach (Tenant::query()->active()->orderBy('name')->get() as $tenant) {
            $from = CarbonImmutable::parse($month->format('Y-m-01'), $tenant->default_timezone);
            $to = $from->endOfMonth();

            [$onTime, $verification] = app(TenantContext::class)->run($tenant, fn (): array => [
                PilotMetrics::onTimePayment($from, $to),
                PilotMetrics::verificationTime($from, $to),
            ]);

            $rows[$tenant->id] = [
                'name' => $tenant->name,
                'on_time' => $onTime['percent'] === null ? 'Belum ada tagihan jatuh tempo' : "{$onTime['percent']}%",
                'on_time_count' => $onTime['on_time'],
                'due' => $onTime['due'],
                'verification' => $verification['median_seconds'] === null
                    ? 'Belum ada'
                    : CarbonInterval::seconds($verification['median_seconds'])->cascade()->locale('id')->forHumans(['parts' => 2]),
                'verified' => $verification['verified'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(): array
    {
        $options = [];
        $month = CarbonImmutable::now('Asia/Jakarta')->startOfMonth();

        for ($i = 0; $i < 12; $i++) {
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
            $month = $month->subMonth();
        }

        return $options;
    }
}
