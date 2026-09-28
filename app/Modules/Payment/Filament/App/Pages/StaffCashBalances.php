<?php

namespace App\Modules\Payment\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\CashHandovers\CashHandoverResource;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\PropertyUser;
use App\Support\Filament\MoneyColumn;
use BackedEnum;
use Filament\Actions\Action;
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
 * Cash each staff member holds per property (FR-PAY-07): cash received,
 * less what was handed over and what was spent from it. Staff see their
 * own; the owner sees everyone.
 */
class StaffCashBalances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Pembayaran';

    protected static ?string $navigationLabel = 'Kas di tangan staf';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'kas-staf';

    protected static ?string $title = 'Kas di tangan staf';

    public static function canAccess(): bool
    {
        return User::current()->can('viewAny', StaffCashHandover::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('handovers')
                ->label('Lihat setoran')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->color('gray')
                ->url(CashHandoverResource::getUrl('index')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => self::rows())
            ->columns([
                TextColumn::make('staff')
                    ->label('Staf')
                    ->description(fn (array $record): string => $record['property']),
                MoneyColumn::make('received')->label('Tunai diterima'),
                MoneyColumn::make('handed_over')->label('Sudah disetor'),
                MoneyColumn::make('spent')->label('Dipakai untuk pengeluaran'),
                MoneyColumn::make('balance')
                    ->label('Masih dipegang')
                    ->weight('bold')
                    ->color(fn (array $record): ?string => $record['balance'] < 0 ? 'danger' : null),
            ])
            ->emptyStateIcon(Heroicon::OutlinedWallet)
            ->emptyStateHeading('Tidak ada staf yang memegang kas')
            ->emptyStateDescription('Staf yang ditugaskan di properti dan menerima uang tunai muncul di sini.');
    }

    /**
     * @return array<string, array{staff: string, property: string, received: int, handed_over: int, spent: int, balance: int}>
     */
    private static function rows(): array
    {
        $viewer = User::current();
        $everyone = $viewer->can(PaymentPermission::ConfirmHandovers->value);
        $rows = [];

        $assignments = PropertyUser::query()
            ->whereIn('property_id', Property::query()->accessibleBy($viewer)->select('id'))
            ->when(! $everyone, fn ($query) => $query->where('user_id', $viewer->id))
            ->with(['user', 'property'])
            ->get();

        foreach ($assignments as $assignment) {
            $staff = $assignment->user;

            if ($staff === null || ! $staff->is_active || ! StaffCash::tracks($staff)) {
                continue;
            }

            $received = StaffCash::received($staff->id, $assignment->property_id);
            $handedOver = StaffCash::handedOver($staff->id, $assignment->property_id);
            $spent = StaffCash::spent($staff->id, $assignment->property_id);

            $rows[$assignment->id] = [
                'staff' => $staff->name,
                'property' => $assignment->property->name ?? '-',
                'received' => $received,
                'handed_over' => $handedOver,
                'spent' => $spent,
                'balance' => $received - $handedOver - $spent,
            ];
        }

        uasort($rows, fn (array $a, array $b): int => $b['balance'] <=> $a['balance']);

        return $rows;
    }
}
