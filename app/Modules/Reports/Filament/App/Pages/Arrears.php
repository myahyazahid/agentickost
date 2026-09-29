<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Enums\BillingPermission;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Modules\Reports\Support\Overdue;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Who owes what past the due date, one row per contract (FR-RPT-02). A
 * contract has one payer and one bill, so each row is a room's residents
 * and the person to chase. Ended contracts with a final bill still unpaid
 * stay on the list.
 */
class Arrears extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Tagihan';

    protected static ?string $navigationLabel = 'Tunggakan';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'tunggakan';

    protected static ?string $title = 'Tunggakan per penghuni';

    public static function canAccess(): bool
    {
        return User::current()->can(BillingPermission::ViewInvoices->value);
    }

    public function getSubheading(): string
    {
        $overdue = Overdue::invoices(User::current());
        $amount = (int) (clone $overdue)->sum('balance_amount');
        $contracts = (clone $overdue)->distinct()->count('contract_id');

        return $contracts === 0
            ? 'Tidak ada tagihan yang lewat jatuh tempo.'
            : 'Total '.Rupiah::format($amount)." dari {$contracts} kontrak. Denda yang sudah terhitung ikut dijumlahkan.";
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::query())
            ->columns([
                TextColumn::make('room.number')
                    ->label('Kamar')
                    ->description(fn (Contract $record): ?string => $record->property?->name),
                TextColumn::make('resident')
                    ->label('Penghuni')
                    ->state(fn (Contract $record): string => $record->residents->pluck('full_name')->implode(', '))
                    ->description(fn (Contract $record): ?string => $record->status->isRunning() ? null : 'Kontrak '.mb_strtolower($record->status->getLabel()))
                    ->wrap(),
                TextColumn::make('payer.name')
                    ->label('Pembayar')
                    ->description(fn (Contract $record): ?string => $record->payer?->phone)
                    ->searchable(),
                TextColumn::make('overdue_invoices_count')
                    ->label('Tagihan telat')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('oldest_due_date')
                    ->label('Telat sejak')
                    ->date('j M Y')
                    ->description(fn (Contract $record): string => self::daysLate($record))
                    ->sortable(),
                MoneyColumn::make('arrears_amount')
                    ->label('Tunggakan')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->defaultSort('arrears_amount', 'desc')
            ->filters([
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([
                Action::make('pay')
                    ->label('Catat pembayaran')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->visible(fn (): bool => User::current()->can(PaymentPermission::RecordPayments->value))
                    ->url(fn (Contract $record): string => PaymentResource::getUrl('create', ['kontrak' => $record->id])),
            ])
            ->recordUrl(fn (Contract $record): string => ContractResource::getUrl('view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle)
            ->emptyStateHeading('Tidak ada tunggakan')
            ->emptyStateDescription('Semua tagihan yang sudah jatuh tempo lunas. Tagihan yang belum jatuh tempo tidak dihitung di sini.');
    }

    /**
     * Contracts with overdue invoices, each with the sum owed, how many
     * invoices, and the oldest due date among them.
     *
     * @return Builder<Contract>
     */
    public static function query(): Builder
    {
        $overdue = fn (): Builder => Overdue::invoices(User::current())->whereColumn('invoices.contract_id', 'contracts.id');

        return Contract::query()
            ->accessibleBy(User::current())
            ->whereExists($overdue()->select(DB::raw(1)))
            ->select('contracts.*')
            ->selectSub($overdue()->selectRaw('SUM(balance_amount)'), 'arrears_amount')
            ->selectSub($overdue()->selectRaw('COUNT(*)'), 'overdue_invoices_count')
            ->selectSub($overdue()->selectRaw('MIN(due_date)'), 'oldest_due_date')
            ->with(['room', 'property', 'payer', 'residents' => fn ($query) => $query->orderByPivot('is_primary', 'desc')]);
    }

    private static function daysLate(Contract $contract): string
    {
        $oldest = $contract->getAttribute('oldest_due_date');
        $property = $contract->property;

        if (! is_string($oldest) || ! $property instanceof Property) {
            return '';
        }

        $days = (int) CarbonImmutable::parse($oldest)->diffInDays($property->today());

        return "{$days} hari";
    }
}
