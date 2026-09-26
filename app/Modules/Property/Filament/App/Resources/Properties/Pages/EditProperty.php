<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Property\Actions\DeleteProperty;
use App\Modules\Property\Actions\UpdateProperty;
use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Property>
 */
class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Pengaturan tagihan')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(fn (): string => PropertyResource::getUrl('settings', ['record' => $this->getRecord()]))
                ->visible(fn (): bool => User::current()->can('manageSettings', $this->getRecord())),
            DeleteAction::make()
                ->using(function (DeleteAction $action, Property $record): bool {
                    DomainActions::forAction($action, fn () => app(DeleteProperty::class)->handle($record));

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['photos'] = $this->getRecord()->attachmentPaths(AttachmentCollection::Photo);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(UpdateProperty::class)->handle($this->getRecord(), $data));
    }
}
