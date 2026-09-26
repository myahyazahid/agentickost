<?php

namespace App\Modules\Billing\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Actions\IssueDueInvoices;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\RentInvoiceGenerator;
use App\Modules\Billing\Support\UpcomingInvoice;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rent invoices that will be issued in the coming days, checked before they
 * go out in bulk. Invoices already due can be issued right away.
 */
class UpcomingInvoices extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Tagihan';

    protected static ?string $navigationLabel = 'Pratinjau tagihan';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'pratinjau-tagihan';

    protected static ?string $title = 'Pratinjau tagihan';

    public static function canAccess(): bool
    {
        return User::current()->can('viewAny', Invoice::class);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?array $filters): array => $this->rows(
                (int) ($filters['days']['value'] ?? 30),
                $filters['property_id']['value'] ?? null,
            ))
            ->columns([
                TextColumn::make('issue_date')
                    ->label('Terbit')
                    ->date('j M Y')
                    ->description(fn (array $record): ?string => $record['is_due'] ? 'Sudah waktunya' : null)
                    ->icon(fn (array $record): ?Heroicon => $record['is_due'] ? Heroicon::OutlinedClock : null)
                    ->color(fn (array $record): ?string => $record['is_due'] ? 'warning' : null),
                TextColumn::make('payer')
                    ->label('Pembayar')
                    ->description(fn (array $record): string => $record['room']),
                TextColumn::make('period')
                    ->label('Periode')
                    ->description(fn (array $record): string => 'Jatuh tempo '.CarbonImmutable::parse($record['due_date'])->translatedFormat('j M Y')),
                MoneyColumn::make('rent')
                    ->label('Sewa')
                    ->description(fn (array $record): ?string => $record['prorated']),
                MoneyColumn::make('extras')
                    ->label('Lainnya')
                    ->description(fn (array $record): ?string => $record['extras_note'])
                    ->placeholder('-'),
                MoneyColumn::make('total')->label('Total')->weight('bold'),
            ])
            ->filters([
                SelectFilter::make('days')
                    ->label('Terbit dalam')
                    ->options([7 => '7 hari', 14 => '14 hari', 30 => '30 hari'])
                    ->default(30)
                    ->selectablePlaceholder(false),
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->headerActions([
                $this->issueDueAction(),
            ])
            ->paginated(false)
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->emptyStateHeading('Tidak ada tagihan sewa yang akan terbit')
            ->emptyStateDescription('Tagihan muncul di sini beberapa hari sebelum terbit, sesuai pengaturan properti.');
    }

    private function issueDueAction(): Action
    {
        return Action::make('issueDue')
            ->label('Terbitkan yang sudah waktunya')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->requiresConfirmation()
            ->modalDescription('Tagihan yang tanggal terbitnya sudah lewat diterbitkan sekarang, tanpa menunggu proses otomatis berikutnya.')
            ->visible(fn (): bool => User::current()->can('create', Invoice::class) && $this->dueContracts() !== [])
            ->action(function (Action $action): void {
                $issued = 0;

                DomainActions::forAction($action, function () use (&$issued): void {
                    foreach ($this->dueContracts() as $contract) {
                        $issued += count(app(IssueDueInvoices::class)->handle($contract));
                    }
                });

                Notification::make()->success()->title("{$issued} tagihan terbit")->send();
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(int $days, ?string $propertyId): array
    {
        $generator = app(RentInvoiceGenerator::class);
        $rows = [];

        foreach ($this->runningContracts($propertyId) as $contract) {
            /** @var Property $property */
            $property = $contract->property;
            $today = $property->today();

            foreach ($generator->upcoming($contract, $today->addDays($days), $today) as $upcoming) {
                $rows[] = self::row($upcoming, $today);
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['issue_date'], $a['room']] <=> [$b['issue_date'], $b['room']]);

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(UpcomingInvoice $upcoming, CarbonImmutable $today): array
    {
        $period = $upcoming->period;
        $contract = $upcoming->contract;

        return [
            'key' => "{$contract->id}:{$period->start->toDateString()}",
            'issue_date' => $period->issueDate->toDateString(),
            'is_due' => $period->issueDate->lessThanOrEqualTo($today),
            'payer' => $contract->payer?->name,
            'room' => "Kamar {$contract->room?->number}, {$contract->property?->name}",
            'period' => $period->start->translatedFormat('j M').' sampai '.$period->end->translatedFormat('j M Y'),
            'due_date' => $period->dueDate->toDateString(),
            'rent' => $upcoming->rent,
            'prorated' => $period->isPartial() ? "Prorata {$period->billedDays}/{$period->basisDays} hari" : null,
            'extras' => ($upcoming->deposit + $upcoming->utilities) ?: null,
            'extras_note' => match (true) {
                $upcoming->deposit > 0 && $upcoming->utilities > 0 => 'Deposit dan utilitas',
                $upcoming->deposit > 0 => 'Deposit',
                $upcoming->utilities > 0 => 'Utilitas, perkiraan',
                default => null,
            },
            'total' => $upcoming->total(),
        ];
    }

    /**
     * @return list<Contract>
     */
    private function dueContracts(): array
    {
        $generator = app(RentInvoiceGenerator::class);

        return array_values(array_filter(
            $this->runningContracts(null),
            fn (Contract $contract): bool => $generator->duePeriods($contract, $contract->property?->today() ?? CarbonImmutable::now()) !== [],
        ));
    }

    /**
     * @return list<Contract>
     */
    private function runningContracts(?string $propertyId): array
    {
        return array_values(Contract::query()
            ->accessibleBy(User::current())
            ->whereIn('status', ContractState::runningValues())
            ->when($propertyId, fn ($query) => $query->where('property_id', $propertyId))
            ->with(['property', 'room', 'payer'])
            ->get()
            ->all());
    }
}
