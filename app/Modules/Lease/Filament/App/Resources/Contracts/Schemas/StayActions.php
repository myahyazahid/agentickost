<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\Schemas;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Lease\Actions\FinalizeSettlement;
use App\Modules\Lease\Actions\MoveRoom;
use App\Modules\Lease\Actions\RecordCheckIn;
use App\Modules\Lease\Actions\RecordCheckOut;
use App\Modules\Lease\Enums\InspectionType;
use App\Modules\Lease\Enums\ItemCondition;
use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\Active;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Lease\States\Contract\Notice;
use App\Modules\Lease\States\Contract\Terminated;
use App\Modules\Lease\Support\InspectionItems;
use App\Modules\Lease\Support\SettlementCalculator;
use App\Modules\Property\Models\Room;
use App\Modules\Property\States\Room\Available;
use App\Modules\Property\Support\RoomPricing;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

/**
 * Check-in, room move, check-out, and settlement buttons for the contract
 * page (FR-SIK-01 to FR-SIK-05). Each runs the Lease action of the same name.
 */
final class StayActions
{
    /**
     * @param  Closure(): Contract  $contract
     * @param  Closure(Action, Closure(): mixed, string): void  $run
     */
    public static function checkIn(Closure $contract, Closure $run): Action
    {
        return Action::make('checkIn')
            ->label('Check-in')
            ->icon(Heroicon::OutlinedKey)
            ->visible(fn (): bool => $contract()->status->equals(Draft::class, Active::class)
                && ! $contract()->inspections()->where('type', InspectionType::CheckIn->value)->exists()
                && User::current()->can('inspect', $contract())
                && ($contract()->status->equals(Active::class) || User::current()->can('update', $contract())))
            ->modalHeading('Check-in')
            ->modalDescription(fn (): string => $contract()->status->equals(Draft::class)
                ? 'Catat kondisi kamar saat penghuni masuk. Kontrak ikut diaktifkan dan kamar ditandai terisi.'
                : 'Catat kondisi kamar saat penghuni masuk, sebagai pembanding saat check-out.')
            ->fillForm(fn (): array => ['inspected_on' => now()->toDateString(), 'items' => InspectionItems::defaultRows()])
            ->schema([
                DatePicker::make('inspected_on')->label('Tanggal check-in')->maxDate(now())->required(),
                self::checklist(withCharges: false),
                AttachmentUpload::make('photos', AttachmentCollection::Inspection)->label('Foto kamar')->image()->maxFiles(10),
                Textarea::make('notes')->label('Catatan'),
                Toggle::make('resident_acknowledged')
                    ->label('Penghuni sudah memeriksa dan setuju')
                    ->helperText('Bisa ditandai belakangan dari daftar pemeriksaan kamar.'),
            ])
            ->modalSubmitActionLabel('Simpan check-in')
            ->action(fn (Action $action, array $data) => $run($action, fn () => app(RecordCheckIn::class)->handle($contract(), self::rows($data)), 'Check-in dicatat'));
    }

    /**
     * @param  Closure(): Contract  $contract
     * @param  Closure(Action, Closure(): mixed, string): void  $run
     */
    public static function moveRoom(Closure $contract, Closure $run): Action
    {
        return Action::make('moveRoom')
            ->label('Pindah kamar')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('gray')
            ->visible(fn (): bool => $contract()->status->equals(Active::class)
                && ! $contract()->renewal()->exists()
                && User::current()->can('update', $contract()))
            ->modalDescription('Kamar lama ditagih sampai sehari sebelum pindah; sisa sewa yang sudah dibayar menjadi saldo kredit. Kamar baru ditagih prorata dari tanggal pindah.')
            ->fillForm(fn (): array => [
                'moved_on' => now()->toDateString(),
                'new_deposit_amount' => $contract()->deposit_amount,
            ])
            ->schema([
                Select::make('to_room_id')
                    ->label('Kamar baru')
                    ->options(fn (): array => self::freeRooms($contract()))
                    ->required()
                    ->searchable()
                    ->live(),
                DatePicker::make('moved_on')->label('Tanggal pindah')->maxDate(now())->required()->live(),
                MoneyInput::make('new_rent_amount')
                    ->label('Sewa kamar baru per periode')
                    ->placeholder(fn (Get $get): string => self::priceHint($contract(), $get('to_room_id'), $get('moved_on')))
                    ->helperText('Kosongkan untuk memakai harga kamar yang berlaku.'),
                MoneyInput::make('new_deposit_amount')
                    ->label('Deposit kamar baru')
                    ->helperText(fn (): string => 'Deposit sekarang '.Rupiah::format($contract()->deposit_amount).'. Bila lebih besar, selisihnya ditagih; bila lebih kecil, kelebihannya tetap dipegang sampai check-out.'),
                Toggle::make('old_room_needs_maintenance')->label('Kamar lama perlu perbaikan dulu'),
                Textarea::make('notes')->label('Catatan'),
            ])
            ->modalSubmitActionLabel('Pindahkan')
            ->action(fn (Action $action, array $data) => $run($action, fn () => app(MoveRoom::class)->handle($contract(), $data), 'Penghuni dipindah ke kamar baru'));
    }

    /**
     * @param  Closure(): Contract  $contract
     * @param  Closure(Action, Closure(): mixed, string): void  $run
     */
    public static function checkOut(Closure $contract, Closure $run): Action
    {
        return Action::make('checkOut')
            ->label('Check-out')
            ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)
            ->color('warning')
            ->visible(fn (): bool => self::canCheckOut($contract()) && User::current()->can('inspect', $contract()))
            ->modalHeading('Check-out')
            ->modalDescription('Periksa kamar dan catat biaya kerusakan. Hasilnya menjadi draf penyelesaian yang diselesaikan owner.')
            ->fillForm(fn (): array => [
                'moved_out_on' => now()->toDateString(),
                'room_after' => RoomAfterCheckOut::Available->value,
                'early_termination_amount' => app(SettlementCalculator::class)->proposedPenalty($contract()),
                'items' => self::checkInItems($contract()),
            ])
            ->schema([
                DatePicker::make('moved_out_on')->label('Tanggal keluar')->maxDate(now())->required(),
                self::checklist(withCharges: true),
                AttachmentUpload::make('photos', AttachmentCollection::Inspection)->label('Foto kamar')->image()->maxFiles(10),
                MoneyInput::make('early_termination_amount')
                    ->label('Penalti keluar sebelum waktunya')
                    ->helperText('Terisi dari kontrak bila pemberitahuan keluar kurang dari masa pemberitahuan properti atau kontrak diputus.'),
                Radio::make('room_after')->label('Setelah check-out, kamar')->options(RoomAfterCheckOut::class)->required(),
                Textarea::make('notes')->label('Catatan'),
            ])
            ->modalSubmitActionLabel('Simpan check-out')
            ->action(fn (Action $action, array $data) => $run($action, fn () => app(RecordCheckOut::class)->handle($contract(), self::rows($data)), 'Check-out dicatat, penyelesaian menunggu owner'));
    }

    /**
     * @param  Closure(): Contract  $contract
     * @param  Closure(Action, Closure(): mixed, string): void  $run
     */
    public static function finalizeSettlement(Closure $contract, Closure $run): Action
    {
        return Action::make('finalizeSettlement')
            ->label('Selesaikan check-out')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (): bool => ($contract()->settlement?->isDraft() ?? false) && User::current()->can('finalizeSettlement', $contract()))
            ->modalHeading('Selesaikan check-out?')
            ->modalDescription('Sisa sewa dan tagihan akhir diterbitkan, saldo kredit lalu deposit dipakai melunasi tunggakan, dan sisanya dikembalikan. Kontrak selesai dan kamar dilepas. Langkah ini tidak bisa dibatalkan.')
            ->fillForm(fn (): array => ['early_termination_amount' => $contract()->settlement?->early_termination_amount])
            ->schema([
                Text::make(fn (): string => self::settlementHint($contract())),
                MoneyInput::make('early_termination_amount')->label('Penalti keluar sebelum waktunya'),
                Select::make('refund_account_id')
                    ->label('Pengembalian dibayar dari')
                    ->options(fn (): array => RefundDeposit::payoutAccounts()->pluck('name', 'id')->all())
                    ->helperText('Wajib bila ada deposit atau saldo kredit yang dikembalikan.')
                    ->native(false),
            ])
            ->modalSubmitActionLabel('Selesaikan')
            ->action(fn (Action $action, array $data) => $run(
                $action,
                fn () => app(FinalizeSettlement::class)->handle($contract()->settlement()->firstOrFail(), $data),
                'Check-out selesai',
            ));
    }

    private static function checklist(bool $withCharges): Repeater
    {
        return Repeater::make('items')
            ->label('Kondisi kamar')
            ->table([
                TableColumn::make('Barang'),
                TableColumn::make('Kondisi')->width('22%'),
                ...($withCharges ? [TableColumn::make('Biaya')->width('22%')] : []),
                TableColumn::make('Catatan'),
            ])
            ->schema([
                TextInput::make('item_name')->required()->maxLength(100),
                Select::make('condition')->options(ItemCondition::class)->required()->native(false),
                ...($withCharges ? [MoneyInput::make('charge_amount')] : []),
                TextInput::make('notes')->maxLength(500),
            ])
            ->minItems(1)
            ->addActionLabel('Tambah barang');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function rows(array $data): array
    {
        return [...$data, 'items' => array_values($data['items'] ?? [])];
    }

    public static function canCheckOut(Contract $contract): bool
    {
        if ($contract->settlement()->exists()) {
            return false;
        }

        return $contract->status->equals(Notice::class, Terminated::class)
            || ($contract->status->equals(Active::class) && $contract->end_date !== null && ! $contract->end_date->isFuture());
    }

    /**
     * The check-in checklist, so check-out compares the same items.
     *
     * @return list<array{item_name: string, condition: string}>
     */
    private static function checkInItems(Contract $contract): array
    {
        $checkIn = $contract->inspections()->where('type', InspectionType::CheckIn->value)->first();

        if ($checkIn === null) {
            return InspectionItems::defaultRows();
        }

        return array_values($checkIn->items()->get()->map(fn ($item): array => [
            'item_name' => $item->item_name,
            'condition' => ItemCondition::Good->value,
        ])->all());
    }

    /**
     * @return array<string, string>
     */
    private static function freeRooms(Contract $contract): array
    {
        return Room::query()
            ->where('property_id', $contract->property_id)
            ->whereKeyNot($contract->room_id)
            ->where('status', Available::$name)
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn (Room $room): array => [$room->id => "Kamar {$room->number} (untuk {$room->capacity} orang)"])
            ->all();
    }

    private static function priceHint(Contract $contract, ?string $roomId, ?string $movedOn): string
    {
        $room = $roomId === null ? null : Room::query()->find($roomId);

        if ($room === null) {
            return 'Pilih kamar baru';
        }

        $price = app(RoomPricing::class)->priceFor($room, $contract->rental_period, CarbonImmutable::parse($movedOn ?? now()->toDateString()));

        return $price === null ? 'Kamar ini belum punya harga' : 'Harga berlaku '.Rupiah::format($price);
    }

    private static function settlementHint(Contract $contract): string
    {
        $settlement = $contract->settlement;

        if ($settlement === null) {
            return '';
        }

        $result = $settlement->result_amount;

        return 'Perkiraan: '.($result >= 0
            ? 'penghuni masih harus membayar '.Rupiah::format($result)
            : 'dikembalikan ke penghuni '.Rupiah::format(-$result)).'. Angka pasti dihitung saat diselesaikan.';
    }
}
