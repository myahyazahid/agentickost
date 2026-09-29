<?php

namespace App\Modules\Payment\Filament\App\Resources\CashHandovers\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Models\Account;
use App\Modules\Payment\Actions\RecordCashHandover;
use App\Modules\Payment\Enums\PaymentPermission;
use App\Modules\Payment\Filament\App\Resources\CashHandovers\CashHandoverResource;
use App\Modules\Payment\Models\StaffCashHandover;
use App\Modules\Payment\Support\StaffCash;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class ListCashHandovers extends ListRecords
{
    protected static string $resource = CashHandoverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('handOver')
                ->label('Catat setoran')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->visible(fn (): bool => User::current()->can('create', StaffCashHandover::class))
                ->modalHeading('Catat setoran kas')
                ->modalDescription('Uang tunai yang diterima dari penghuni diserahkan ke kas atau rekening owner.')
                ->schema([
                    Select::make('property_id')
                        ->label('Properti')
                        ->options(fn (): array => PropertyOptions::properties())
                        ->default(fn (): ?string => count($options = PropertyOptions::properties()) === 1 ? array_key_first($options) : null)
                        ->required()
                        ->live(),
                    Select::make('staff_user_id')
                        ->label('Staf yang menyetor')
                        ->options(fn (Get $get): array => self::staffOptions($get('property_id')))
                        ->default(fn (): ?string => StaffCash::tracks(User::current()) ? User::current()->id : null)
                        ->visible(fn (): bool => User::current()->can(PaymentPermission::ConfirmHandovers->value))
                        ->required()
                        ->live(),
                    Text::make(fn (Get $get): string => self::heldHint($get('property_id'), $get('staff_user_id'))),
                    MoneyInput::make('actual_amount')->label('Jumlah disetor')->required()->minValue(1),
                    Select::make('destination_account_id')
                        ->label('Disetor ke')
                        ->options(fn (): array => RefundDeposit::payoutAccounts()->pluck('name', 'id')->all())
                        ->default(fn (): ?string => Account::query()->where('subtype', AccountSubtype::Cash->value)->value('id'))
                        ->required()
                        ->native(false),
                    DateTimePicker::make('handed_over_at')
                        ->label('Waktu setor')
                        ->seconds(false)
                        ->default(now())
                        ->maxDate(now()->endOfDay())
                        ->required(),
                ])
                ->modalSubmitActionLabel('Simpan setoran')
                ->action(function (Action $action, array $data): void {
                    $property = Property::query()->accessibleBy(User::current())->whereKey($data['property_id'])->firstOrFail();

                    DomainActions::forAction($action, fn () => app(RecordCashHandover::class)->handle($property, $data));

                    Notification::make()->success()->title('Setoran dicatat, menunggu diterima owner')->send();
                }),
        ];
    }

    /**
     * Staff assigned to the property who can hold cash.
     *
     * @return array<string, string>
     */
    private static function staffOptions(?string $propertyId): array
    {
        $property = Property::query()->find($propertyId);

        if ($property === null) {
            return [];
        }

        return $property->staff()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $staff): bool => StaffCash::tracks($staff))
            ->mapWithKeys(fn (User $staff): array => [$staff->id => $staff->name])
            ->all();
    }

    private static function heldHint(?string $propertyId, ?string $staffId): string
    {
        $user = User::current();

        // Both values come from form state: only a property the user can see,
        // and only their own cash unless they confirm handovers.
        if ($staffId === null || ! $user->can(PaymentPermission::ConfirmHandovers->value)) {
            $staffId = $user->id;
        }

        if ($propertyId === null || ! Property::query()->accessibleBy($user)->whereKey($propertyId)->exists()) {
            return 'Pilih properti untuk melihat saldo kas.';
        }

        return 'Saldo kas di tangan saat ini '.Rupiah::format(StaffCash::balance($staffId, $propertyId)).'. Bila jumlah disetor berbeda, selisihnya ditandai.';
    }
}
