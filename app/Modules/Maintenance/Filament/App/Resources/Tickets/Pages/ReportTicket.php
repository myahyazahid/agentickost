<?php

namespace App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Maintenance\Actions\ReportTicket as ReportTicketAction;
use App\Modules\Maintenance\Enums\TicketCategory;
use App\Modules\Maintenance\Enums\TicketPriority;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\TicketResource;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Modules\Property\Models\Room;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Reporting a problem, built to be filled in on a phone in the corridor.
 */
class ReportTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected static ?string $title = 'Laporkan kerusakan';

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('room_id', null)),
                    Select::make('room_id')
                        ->label('Kamar')
                        ->placeholder('Area umum')
                        ->options(fn (Get $get): array => Room::query()->accessibleBy(User::current())->where('property_id', $get('property_id') ?? '')->orderBy('number')->pluck('number', 'id')->map(fn (string $number): string => "Kamar {$number}")->all())
                        ->searchable()
                        ->helperText('Kosongkan untuk lorong, dapur, atau area umum lain.'),
                    TextInput::make('title')->label('Masalahnya')->placeholder('Misal: keran kamar mandi bocor')->required()->maxLength(150)->columnSpanFull(),
                    Select::make('category')->label('Kategori')->options(TicketCategory::class)->required()->native(false),
                    Radio::make('priority')
                        ->label('Prioritas')
                        ->options(TicketPriority::class)
                        ->default(TicketPriority::Normal->value)
                        ->inline()
                        ->required(),
                    Textarea::make('description')->label('Keterangan')->required()->rows(3)->columnSpanFull(),
                    AttachmentUpload::make('photos', AttachmentCollection::Before)
                        ->label('Foto kerusakan')
                        ->maxFiles(5)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $property = Property::query()->accessibleBy(User::current())->whereKey($data['property_id'] ?? null)->first();

        return DomainActions::forForm(function () use ($property, $data): Model {
            abort_if($property === null, 404);

            return app(ReportTicketAction::class)->handle($property, $data);
        });
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Kirim laporan');
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
