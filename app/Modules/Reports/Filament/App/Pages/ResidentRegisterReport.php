<?php

namespace App\Modules\Reports\Filament\App\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\ExportResidentRegister;
use App\Modules\Lease\Enums\LeasePermission;
use App\Modules\Lease\Models\Resident;
use App\Modules\Lease\Support\ResidentRegister;
use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Modules\Reports\Filament\App\ReportPage;
use App\Support\Filament\ReportDownloads;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRow;
use App\Support\Reports\ReportSheet;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The residents living in a property now, for the RT/RW (FR-PNH-07). The
 * screen shows the identity type only; the downloaded file carries the
 * identity numbers for staff allowed to see them, and is logged.
 */
class ResidentRegisterReport extends ReportPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Penghuni';

    protected static ?string $navigationLabel = 'Data untuk RT/RW';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'laporan/data-penghuni-rt-rw';

    protected static ?string $title = 'Data penghuni untuk RT/RW';

    public static function canAccess(): bool
    {
        return User::current()->can('viewAny', Resident::class);
    }

    public function getSubheading(): string
    {
        return User::current()->can(LeasePermission::ViewIdentity->value)
            ? 'Berkas unduhan memuat nomor identitas dan tercatat di log audit.'
            : 'Nomor identitas tidak ikut karena peran Anda tidak boleh melihatnya.';
    }

    protected static function columns(): array
    {
        return [
            'no' => ReportColumn::text('No'),
            'name' => ReportColumn::text('Nama'),
            'gender' => ReportColumn::text('Jenis kelamin'),
            'birth_date' => ReportColumn::text('Tanggal lahir'),
            'identity' => ReportColumn::text('Identitas'),
            'phone' => ReportColumn::text('Telepon'),
            'institution' => ReportColumn::text('Kampus atau kantor'),
            'vehicle' => ReportColumn::text('Kendaraan'),
            'room' => ReportColumn::text('Kamar'),
            'since' => ReportColumn::text('Tinggal sejak'),
        ];
    }

    protected function filterComponents(): array
    {
        return [
            Select::make('property_id')
                ->label('Properti')
                ->options(fn (): array => PropertyOptions::properties())
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
        ];
    }

    protected function defaultFilters(): array
    {
        return ['property_id' => array_key_first(PropertyOptions::properties())];
    }

    public function sheet(): ReportSheet
    {
        $property = $this->property();

        return $this->sheetOf($property, $property === null ? [] : ResidentRegister::rows($property));
    }

    protected function getHeaderActions(): array
    {
        return [ReportDownloads::make(function (): ReportSheet {
            $property = $this->property();

            return $this->sheetOf($property, $property === null ? [] : app(ExportResidentRegister::class)->handle($property));
        })];
    }

    private function property(): ?Property
    {
        $id = $this->filterPropertyId();

        return $id === null ? null : Property::query()->accessibleBy(User::current())->whereKey($id)->first();
    }

    /**
     * @param  list<array<string, string|int|null>>  $rows
     */
    private function sheetOf(?Property $property, array $rows): ReportSheet
    {
        return new ReportSheet(
            'Data penghuni',
            ($property === null ? 'Pilih properti' : "{$property->name}, {$property->address}, {$property->city}").'. Per '.self::today()->translatedFormat('j F Y'),
            static::columns(),
            array_map(fn (array $row): ReportRow => ReportRow::line($row), $rows),
            'data-penghuni-'.($property->code ?? 'properti').'-'.self::today()->format('Ymd'),
        );
    }
}
