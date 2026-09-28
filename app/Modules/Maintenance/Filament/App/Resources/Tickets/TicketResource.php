<?php

namespace App\Modules\Maintenance\Filament\App\Resources\Tickets;

use App\Modules\Access\Models\User;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ListTickets;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ReportTicket;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages\ViewTicket;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\Schemas\TicketInfolist;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Reported;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TicketResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Maintenance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tiket';

    protected static ?string $pluralModelLabel = 'tiket perbaikan';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'tiket';

    /**
     * @return Builder<Ticket>
     */
    public static function getEloquentQuery(): Builder
    {
        return Ticket::query()->accessibleBy(User::current());
    }

    /**
     * New tickets waiting for someone to pick them up.
     */
    public static function getNavigationBadge(): ?string
    {
        $new = static::getEloquentQuery()->where('status', Reported::$name)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tiket baru';
    }

    public static function infolist(Schema $schema): Schema
    {
        return TicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Tiket')
                    ->description(fn (Ticket $record): string => "{$record->category->getLabel()}, {$record->location()}, {$record->property?->name}")
                    ->searchable()
                    ->wrap(),
                TextColumn::make('priority')->label('Prioritas')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('assignee.name')->label('Dikerjakan')->placeholder('Belum ada'),
                TextColumn::make('created_at')
                    ->label('Dilaporkan')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['property', 'room', 'assignee']))
            ->filters([
                SelectFilter::make('property_id')->label('Properti')->options(fn (): array => PropertyOptions::properties()),
                SelectFilter::make('category')->label('Kategori')->options(TicketCategory::class),
                SelectFilter::make('priority')->label('Prioritas')->options(TicketPriority::class),
            ])
            ->recordActions([ViewAction::make()->label('Buka')])
            ->emptyStateIcon(Heroicon::OutlinedWrenchScrewdriver)
            ->emptyStateHeading('Tidak ada tiket di sini')
            ->emptyStateDescription('Laporkan kerusakan atau kebutuhan kebersihan kamar dan area umum lewat tombol Laporkan.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => ReportTicket::route('/lapor'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
