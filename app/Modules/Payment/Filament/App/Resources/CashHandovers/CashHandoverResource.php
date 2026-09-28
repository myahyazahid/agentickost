<?php

namespace App\Modules\Payment\Filament\App\Resources\CashHandovers;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\CashHandovers\Pages\ListCashHandovers;
use App\Modules\Payment\Filament\App\Resources\CashHandovers\Tables\CashHandoversTable;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Staff cash handovers (FR-PAY-08). Staff see their own; the owner sees and
 * confirms everyone's.
 */
class CashHandoverResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = StaffCashHandover::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Pembayaran';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'setoran kas';

    protected static ?string $pluralModelLabel = 'setoran kas';

    protected static ?string $slug = 'setoran-kas';

    public static function table(Table $table): Table
    {
        return CashHandoversTable::configure($table);
    }

    /**
     * @return Builder<StaffCashHandover>
     */
    public static function getEloquentQuery(): Builder
    {
        $user = User::current();
        $query = StaffCashHandover::query()->accessibleBy($user);

        return $user->can(PaymentPermission::ConfirmHandovers->value) ? $query : $query->where('staff_user_id', $user->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashHandovers::route('/'),
        ];
    }
}
