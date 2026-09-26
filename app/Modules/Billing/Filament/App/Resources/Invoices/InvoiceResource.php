<?php

namespace App\Modules\Billing\Filament\App\Resources\Invoices;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\CreateAdhocInvoice;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\EditDraftInvoice;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\ListInvoices;
use App\Modules\Billing\Filament\App\Resources\Invoices\Pages\ViewInvoice;
use App\Modules\Billing\Filament\App\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Modules\Billing\Filament\App\Resources\Invoices\Tables\InvoicesTable;
use App\Modules\Billing\Models\Invoice;
use App\Support\Filament\SentenceCaseLabels;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Tagihan';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'tagihan';

    protected static ?string $pluralModelLabel = 'tagihan';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'tagihan';

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    /**
     * @return Builder<Invoice>
     */
    public static function getEloquentQuery(): Builder
    {
        return Invoice::query()->accessibleBy(User::current());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateAdhocInvoice::route('/tambah'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditDraftInvoice::route('/{record}/ubah'),
        ];
    }
}
