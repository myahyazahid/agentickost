<?php

namespace App\Modules\Tenancy\Filament\Admin\Resources\Tenants;

use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantUsage;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every kost business on the platform, with its status and usage
 * (FR-TNT-04).
 */
class TenantResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'tenant';

    protected static ?string $pluralModelLabel = 'tenant';

    protected static ?string $slug = 'tenant';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Tenant>
     */
    public static function getEloquentQuery(): Builder
    {
        return TenantUsage::query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Usaha')
                    ->description(fn (Tenant $record): ?string => $record->getAttribute('owner_email'))
                    ->searchable(['name', 'slug'])
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Tenant $record): TenantStatus => $record->status())
                    ->badge()
                    ->description(fn (Tenant $record): ?string => self::statusDetail($record)),
                TextColumn::make('properties_count')->label('Properti')->numeric()->sortable(),
                TextColumn::make('rooms_count')->label('Kamar')->numeric()->sortable(),
                TextColumn::make('running_contracts_count')->label('Kontrak berjalan')->numeric()->sortable(),
                TextColumn::make('staff_count')->label('Pengguna aktif')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Mendaftar')->date('j M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TenantStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => match (TenantStatus::tryFrom((string) ($data['value'] ?? ''))) {
                        TenantStatus::Frozen => $query->whereNotNull('frozen_at'),
                        TenantStatus::Trial => $query->whereNull('frozen_at')->where('trial_ends_at', '>', now()),
                        TenantStatus::TrialEnded => $query->whereNull('frozen_at')->where('trial_ends_at', '<=', now()),
                        TenantStatus::Active => $query->whereNull('frozen_at')->whereNull('trial_ends_at'),
                        null => $query,
                    }),
            ])
            ->recordUrl(fn (Tenant $record): string => static::getUrl('view', ['record' => $record]))
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2)
            ->emptyStateHeading('Belum ada tenant')
            ->emptyStateDescription('Tenant muncul di sini begitu owner mendaftar di /app/register atau dibuat dengan php artisan tenant:create.');
    }

    public static function statusDetail(Tenant $tenant): ?string
    {
        return match ($tenant->status()) {
            TenantStatus::Trial => 'Sampai '.$tenant->trial_ends_at?->timezone($tenant->default_timezone)->translatedFormat('j M Y').", sisa {$tenant->trialDaysLeft()} hari",
            TenantStatus::TrialEnded => 'Berakhir '.$tenant->trial_ends_at?->timezone($tenant->default_timezone)->translatedFormat('j M Y'),
            TenantStatus::Frozen => 'Sejak '.$tenant->frozen_at?->timezone($tenant->default_timezone)->translatedFormat('j M Y'),
            TenantStatus::Active => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'view' => ViewTenant::route('/{record}'),
        ];
    }
}
