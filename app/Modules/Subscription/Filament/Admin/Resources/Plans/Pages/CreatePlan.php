<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\Plans\Pages;

use App\Modules\Subscription\Actions\SavePlan;
use App\Modules\Subscription\Filament\Admin\Resources\Plans\PlanResource;
use App\Support\Filament\DomainActions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    protected static ?string $title = 'Tambah paket';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DomainActions::forForm(fn () => app(SavePlan::class)->handle($data));
    }
}
