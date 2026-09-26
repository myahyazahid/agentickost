<?php

namespace App\Modules\Property\Filament\App\Resources\Properties\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Property\Actions\UpdatePropertySettings;
use App\Modules\Property\Filament\App\Resources\Properties\PropertyResource;
use App\Modules\Property\Filament\App\Resources\Properties\Schemas\PropertySettingsForm;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use BackedEnum;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Billing rules of one property, stored in property_settings (FR-PRP-04).
 *
 * @extends EditRecord<Property>
 */
class EditPropertySettings extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Pengaturan tagihan {$this->getRecord()->name}";
    }

    public function getBreadcrumb(): string
    {
        return 'Pengaturan tagihan';
    }

    public function form(Schema $schema): Schema
    {
        return PropertySettingsForm::configure($schema);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(User::current()->can('manageSettings', $this->getRecord()), 403);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $settings = $this->getRecord()->resolvedSettings();

        $data = array_map(
            fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value,
            $settings->attributesToArray(),
        );

        $data['allocation_order'] = array_map(
            fn (string $category): array => ['category' => $category],
            $settings->allocation_order,
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $order = is_array($data['allocation_order'] ?? null) ? $data['allocation_order'] : [];
        $data['allocation_order'] = array_values(array_map(
            fn (mixed $item): mixed => is_array($item) ? ($item['category'] ?? null) : $item,
            $order,
        ));

        DomainActions::forForm(fn () => app(UpdatePropertySettings::class)->handle($this->getRecord(), $data));

        return $this->getRecord();
    }

    /**
     * Staff assignment belongs on the property page, not here.
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
