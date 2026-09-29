<?php

namespace App\Modules\Access\Filament\App\Resources\Users\Pages;

use App\Modules\Access\Actions\InviteStaff;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Filament\App\Resources\Users\UserResource;
use App\Modules\Access\Filament\App\Resources\Users\Widgets\PendingInvitations;
use App\Modules\Property\Filament\PropertyOptions;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label('Undang staf')
                ->icon(Heroicon::OutlinedEnvelope)
                ->modalHeading('Undang staf lewat email')
                ->modalDescription('Staf menerima email berisi tautan untuk membuat password. Tautan berlaku 7 hari.')
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(100),
                    TextInput::make('email')->label('Email')->email()->required()->maxLength(150),
                    Select::make('role')
                        ->label('Peran')
                        ->options(self::roleOptions())
                        ->default(Role::Caretaker->value)
                        ->required()
                        ->live(),
                    CheckboxList::make('property_ids')
                        ->label('Properti yang dipegang')
                        ->helperText('Manajer dan penjaga hanya melihat properti yang dicentang.')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->visible(fn (Get $get): bool => ! (Role::tryFrom((string) $get('role'))?->seesAllProperties() ?? false)),
                ])
                ->modalSubmitActionLabel('Kirim undangan')
                ->action(function (Action $action, array $data): void {
                    DomainActions::forAction($action, fn () => app(InviteStaff::class)->handle($data));

                    Notification::make()->success()->title("Undangan dikirim ke {$data['email']}")->send();
                    $this->dispatch('invitations-changed');
                }),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [PendingInvitations::class];
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $options = [];

        foreach (Role::staff() as $role) {
            $options[$role->value] = $role->getLabel();
        }

        return $options;
    }
}
