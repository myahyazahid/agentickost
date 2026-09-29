<?php

namespace App\Modules\Subscription\Filament\Admin\Resources\Subscriptions;

use App\Modules\Subscription\Filament\Admin\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\Subscription\Grace;
use App\Modules\Subscription\States\Subscription\Restricted;
use App\Modules\Subscription\States\Subscription\SubscriptionState;
use App\Modules\Subscription\Support\BillingSettings;
use App\Modules\Tenancy\Scopes\TenantScope;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Where each tenant's subscription stands (PRD §9.6). A tenant appears once
 * it has opened its subscription page or passed through the daily run.
 */
class SubscriptionResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Langganan';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'langganan tenant';

    protected static ?string $pluralModelLabel = 'langganan tenant';

    protected static ?string $slug = 'langganan-tenant';

    public static function canViewAny(): bool
    {
        $admin = Filament::auth()->user();

        return $admin !== null && $admin->can('viewAny', Subscription::class);
    }

    /**
     * @return Builder<Subscription>
     */
    public static function getEloquentQuery(): Builder
    {
        return Subscription::query()->withoutGlobalScope(TenantScope::class)->with(['tenant', 'plan']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')->label('Usaha')->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->description(fn (Subscription $record): ?string => self::statusDetail($record)),
                TextColumn::make('plan.name')
                    ->label('Paket')
                    ->description(fn (Subscription $record): ?string => $record->billing_cycle?->getLabel())
                    ->placeholder('Belum memilih'),
                TextColumn::make('current_period_end')->label('Dibayar sampai')->date('j M Y')->placeholder('-')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SubscriptionState::options()),
            ])
            ->emptyStateIcon(Heroicon::OutlinedCreditCard)
            ->emptyStateHeading('Belum ada langganan')
            ->emptyStateDescription('Langganan tenant tercatat sejak owner membuka halaman Langganan atau sejak proses harian berjalan.');
    }

    private static function statusDetail(Subscription $subscription): ?string
    {
        $timezone = $subscription->tenant->default_timezone ?? config('app.timezone');

        return match (true) {
            $subscription->status->equals(Grace::class) => 'Sampai '.$subscription->grace_ends_at?->timezone($timezone)->subSecond()->translatedFormat('j M Y'),
            $subscription->status->equals(Restricted::class) => 'Dibekukan '.$subscription->read_only_since?->timezone($timezone)->addDays(BillingSettings::readOnlyDays())->translatedFormat('j M Y'),
            $subscription->status->equals(Cancelled::class) => 'Berhenti setelah '.$subscription->current_period_end?->translatedFormat('j M Y'),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
        ];
    }
}
