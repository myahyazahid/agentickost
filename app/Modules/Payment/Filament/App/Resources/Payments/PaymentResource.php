<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\ListPayments;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\RecordPayment;
use App\Modules\Payment\Filament\App\Resources\Payments\Pages\ViewPayment;
use App\Modules\Payment\Filament\App\Resources\Payments\Schemas\PaymentInfolist;
use App\Modules\Payment\Filament\App\Resources\Payments\Tables\PaymentsTable;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Pembayaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?string $pluralModelLabel = 'pembayaran';

    protected static ?string $recordTitleAttribute = 'receipt_number';

    protected static ?string $slug = 'pembayaran';

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    /**
     * @return Builder<Payment>
     */
    public static function getEloquentQuery(): Builder
    {
        return Payment::query()->accessibleBy(User::current());
    }

    /**
     * Payments waiting for verification, shown to those who verify them.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! User::current()->can(PaymentPermission::VerifyPayments->value)) {
            return null;
        }

        $pending = static::getEloquentQuery()->where('status', Pending::$name)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Menunggu verifikasi';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => RecordPayment::route('/catat'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
