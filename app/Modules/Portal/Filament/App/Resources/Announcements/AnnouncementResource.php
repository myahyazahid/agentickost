<?php

namespace App\Modules\Portal\Filament\App\Resources\Announcements;

use App\Modules\Access\Models\User;
use App\Modules\Portal\Filament\App\Resources\Announcements\Pages\CreateAnnouncement;
use App\Modules\Portal\Filament\App\Resources\Announcements\Pages\EditAnnouncement;
use App\Modules\Portal\Filament\App\Resources\Announcements\Pages\ListAnnouncements;
use App\Modules\Portal\Models\Announcement;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Announcements residents read in the portal (FR-PRT-05). Sending them by
 * WhatsApp comes with notifications (M1.5.4).
 */
class AnnouncementResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Penghuni';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'pengumuman';

    protected static ?string $slug = 'pengumuman';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => array_key_first(PropertyOptions::properties()))
                        ->required(),
                    TextInput::make('title')
                        ->label('Judul')
                        ->placeholder('Misal: Air mati Sabtu pagi')
                        ->required()
                        ->maxLength(150),
                    Textarea::make('body')
                        ->label('Isi')
                        ->rows(6)
                        ->required()
                        ->maxLength(5000),
                    Toggle::make('publish')
                        ->label('Tampilkan di portal penghuni')
                        ->helperText('Bila tidak dicentang, pengumuman disimpan sebagai draf.')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->description(fn (Announcement $record): ?string => $record->property?->name)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('published_at')
                    ->label('Tampil sejak')
                    ->dateTime('j M Y H:i')
                    ->placeholder('Draf')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('property_id')
                    ->label('Properti')
                    ->options(fn (): array => PropertyOptions::properties()),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateHeading('Belum ada pengumuman')
            ->emptyStateDescription('Pengumuman yang ditampilkan muncul di portal penghuni properti itu, misalnya jadwal perbaikan atau perubahan aturan.');
    }

    /**
     * @return Builder<Announcement>
     */
    public static function getEloquentQuery(): Builder
    {
        return Announcement::query()
            ->with('property')
            ->whereIn('property_id', Property::query()->accessibleBy(User::current())->select('id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/tulis'),
            'edit' => EditAnnouncement::route('/{record}/ubah'),
        ];
    }
}
