<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Pages;

use App\Modules\Lease\Actions\UpdateDraftContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\ContractResource;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\ContractForm;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Draft;
use App\Support\Filament\DomainActions;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Terms of a draft. Residents and the payer are changed from the contract
 * page; once active the terms are locked.
 *
 * @extends EditRecord<Contract>
 */
class EditDraftContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Ubah draf kontrak kamar '.$this->getRecord()->room?->number;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sewa')->columns(2)->columnSpanFull()->schema(ContractForm::termsFields()),
            Section::make('Ketentuan tambahan')->columnSpanFull()->schema([
                Textarea::make('clauses')->label('Ketentuan')->rows(4),
            ]),
        ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless($this->getRecord()->status->equals(Draft::class), 404);

        parent::authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(UpdateDraftContract::class)->handle($this->getRecord(), $data));
    }

    protected function getRedirectUrl(): string
    {
        return ContractResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
