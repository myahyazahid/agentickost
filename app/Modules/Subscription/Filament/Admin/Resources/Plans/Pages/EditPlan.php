<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages;

use App\Modules\Subscription\Actions\SavePlan;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\PlanResource;
use App\Modules\Subscription\Models\Plan;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends EditRecord<Plan>
 */
class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DomainActions::forForm(fn () => app(SavePlan::class)->handle($data, $this->getRecord()));
    }
}
