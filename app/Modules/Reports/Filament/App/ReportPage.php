<?php

namespace App\Modules\Reports\Filament\App;

use App\Modules\Property\Filament\PropertyOptions;
use App\Modules\Property\Models\Property;
use App\Modules\Tenancy\TenantContext;
use App\Support\Filament\ReportDownloads;
use App\Support\Money\Rupiah;
use App\Support\Reports\ReportColumn;
use App\Support\Reports\ReportRowKind;
use App\Support\Reports\ReportSheet;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * A report shown as a table under its filters, and downloadable as Excel
 * or PDF from the same ReportSheet (FR-RPT-05).
 *
 * @property-read Schema $filtersForm
 */
abstract class ReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    /**
     * The report for the current filters.
     */
    abstract public function sheet(): ReportSheet;

    /**
     * @return array<string, ReportColumn>
     */
    abstract protected static function columns(): array;

    /**
     * @return list<Component>
     */
    abstract protected function filterComponents(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function defaultFilters(): array;

    public function mount(): void
    {
        $this->filtersForm->fill($this->defaultFilters());
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components($this->filterComponents())
            ->columns(['default' => 1, 'md' => 3])
            ->statePath('filters');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $columns = [];

        foreach (static::columns() as $key => $column) {
            $columns[] = TextColumn::make($key)
                ->label($column->label)
                ->weight(fn (array $record): ?FontWeight => $record['__kind'] === ReportRowKind::Line->value ? null : FontWeight::Bold)
                ->formatStateUsing(fn (mixed $state): string => $column->isMoney && is_int($state) ? Rupiah::format($state) : (string) $state)
                ->alignEnd($column->isMoney)
                ->wrap();
        }

        return $table
            ->records(fn (): array => $this->records())
            ->columns($columns)
            ->paginated(false);
    }

    protected function getHeaderActions(): array
    {
        return [ReportDownloads::make(fn (): ReportSheet => $this->sheet())];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function records(): array
    {
        $records = [];

        foreach ($this->sheet()->rows as $index => $row) {
            $key = "row-{$index}";
            $records[$key] = [...$row->cells, '__key' => $key, '__kind' => $row->kind->value];
        }

        return $records;
    }

    protected function filterDate(string $key): CarbonImmutable
    {
        $value = $this->filters[$key] ?? null;

        return is_string($value) && $value !== ''
            ? CarbonImmutable::parse($value)
            : CarbonImmutable::parse((string) ($this->defaultFilters()[$key] ?? 'today'));
    }

    protected function filterPropertyId(): ?string
    {
        $value = $this->filters['property_id'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function propertyField(): Select
    {
        return Select::make('property_id')
            ->label('Properti')
            ->placeholder('Semua properti')
            ->options(fn (): array => PropertyOptions::properties())
            ->live();
    }

    protected static function dateField(string $key, string $label): DatePicker
    {
        return DatePicker::make($key)
            ->label($label)
            ->required()
            ->live();
    }

    protected function propertyLabel(): string
    {
        $id = $this->filterPropertyId();
        $name = $id === null ? null : Property::query()->whereKey($id)->value('name');

        return is_string($name) ? $name : 'Semua properti';
    }

    /**
     * Today in the tenant's time zone, for default periods.
     */
    protected static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(app(TenantContext::class)->tenant()->default_timezone)->toDateString());
    }
}
