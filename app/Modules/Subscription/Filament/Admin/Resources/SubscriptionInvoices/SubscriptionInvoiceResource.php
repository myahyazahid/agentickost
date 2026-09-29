<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\SubscriptionInvoices;

use App\Modules\Subscription\Actions\MarkSubscriptionInvoicePaid;
use App\Modules\Subscription\Actions\VoidSubscriptionInvoice;
use App\Modules\Subscription\Filament\Admin\Resources\SubscriptionInvoices\Pages\ListSubscriptionInvoices;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\SubscriptionInvoice\SubscriptionInvoiceState;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Subscription invoices of every tenant (FR-SUB-03). Until a payment
 * gateway confirms payments, a super admin marks an invoice paid after
 * checking the transfer.
 */
class SubscriptionInvoiceResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = SubscriptionInvoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Langganan';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'tagihan langganan';

    protected static ?string $pluralModelLabel = 'tagihan langganan';

    protected static ?string $slug = 'tagihan-langganan';

    public static function canViewAny(): bool
    {
        $admin = Filament::auth()->user();

        return $admin !== null && $admin->can('viewAny', SubscriptionInvoice::class);
    }

    /**
     * @return Builder<SubscriptionInvoice>
     */
    public static function getEloquentQuery(): Builder
    {
        return SubscriptionInvoice::query()->withoutGlobalScope(TenantScope::class)->with(['tenant', 'plan']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Nomor')
                    ->description(fn (SubscriptionInvoice $record): ?string => $record->tenant?->name)
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Paket')
                    ->description(fn (SubscriptionInvoice $record): string => $record->billing_cycle->getLabel()),
                TextColumn::make('period_start')
                    ->label('Periode')
                    ->formatStateUsing(fn (SubscriptionInvoice $record): string => $record->period_start->translatedFormat('j M Y').' sampai '.$record->period_end->translatedFormat('j M Y')),
                MoneyColumn::make('amount')->label('Nominal'),
                TextColumn::make('due_date')->label('Jatuh tempo')->date('j M Y')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (SubscriptionInvoice $record): ?string => $record->payment_note),
            ])
            ->defaultSort('due_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SubscriptionInvoiceState::options())
                    ->default(Unpaid::$name),
            ])
            ->recordActions([
                self::markPaidAction(),
                self::voidAction(),
            ])
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent)
            ->emptyStateHeading('Tidak ada tagihan langganan')
            ->emptyStateDescription('Tagihan terbit saat owner memilih paket dan menjelang akhir setiap periode.');
    }

    private static function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Tandai lunas')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (SubscriptionInvoice $record): bool => $record->isUnpaid())
            ->modalHeading(fn (SubscriptionInvoice $record): string => "Tandai lunas {$record->number}")
            ->modalDescription('Pastikan transfer sudah masuk ke rekening Agentic Kost. Langganan tenant langsung aktif untuk periode tagihan ini.')
            ->schema([
                DateTimePicker::make('paid_at')
                    ->label('Waktu transfer masuk')
                    ->seconds(false)
                    ->default(now())
                    ->maxDate(now())
                    ->required(),
                TextInput::make('payment_note')
                    ->label('Catatan')
                    ->placeholder('Misal: transfer BCA a.n. Budi')
                    ->maxLength(255),
            ])
            ->action(function (Action $action, SubscriptionInvoice $record, array $data): void {
                DomainActions::forAction($action, fn () => app(MarkSubscriptionInvoicePaid::class)->handle($record, $data));

                Notification::make()->success()->title("{$record->number} lunas")->send();
            });
    }

    private static function voidAction(): Action
    {
        return Action::make('void')
            ->label('Batalkan')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (SubscriptionInvoice $record): bool => $record->isUnpaid())
            ->requiresConfirmation()
            ->modalHeading(fn (SubscriptionInvoice $record): string => "Batalkan {$record->number}?")
            ->modalDescription('Tagihan yang dibatalkan tidak bisa dibayar. Untuk langganan yang masih berjalan, tagihan perpanjangan baru terbit lagi keesokan harinya.')
            ->action(function (Action $action, SubscriptionInvoice $record): void {
                DomainActions::forAction($action, fn () => app(VoidSubscriptionInvoice::class)->handle($record));

                Notification::make()->success()->title("{$record->number} dibatalkan")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionInvoices::route('/'),
        ];
    }
}
