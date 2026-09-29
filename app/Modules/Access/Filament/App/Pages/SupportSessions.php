<?php

namespace App\Modules\Access\Filament\App\Pages;

use App\Modules\Access\Enums\AccessPermission;
use App\Modules\Access\Models\User;
use App\Modules\Tenancy\Models\ImpersonationLog;
use App\Modules\Tenancy\TenantContext;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every time KostPilot staff entered this tenant's panel, and why
 * (FR-TNT-05). Changes made in a session appear in the audit log under the
 * super admin's name.
 */
class SupportSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|UnitEnum|null $navigationGroup = 'Properti';

    protected static ?string $navigationLabel = 'Akses tim KostPilot';

    protected static ?int $navigationSort = 81;

    protected static ?string $slug = 'akses-kostpilot';

    protected static ?string $title = 'Akses tim KostPilot';

    public static function canAccess(): bool
    {
        return User::current()->can(AccessPermission::ViewAuditLog->value);
    }

    private static function timezone(): string
    {
        return app(TenantContext::class)->tenant()->default_timezone;
    }

    public function getSubheading(): string
    {
        return 'Tim KostPilot hanya masuk ke panel Anda dengan alasan tertulis. Setiap sesi tercatat di sini.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ImpersonationLog::query()
                ->where('tenant_id', app(TenantContext::class)->id())
                ->with('platformAdmin'))
            ->columns([
                TextColumn::make('started_at')->label('Mulai')->dateTime('j M Y H:i', self::timezone())->sortable(),
                TextColumn::make('ended_at')->label('Selesai')->dateTime('j M Y H:i', self::timezone())->placeholder('Masih berjalan'),
                TextColumn::make('platformAdmin.name')->label('Petugas KostPilot'),
                TextColumn::make('reason')->label('Alasan')->wrap(),
            ])
            ->defaultSort('started_at', 'desc')
            ->emptyStateIcon(Heroicon::OutlinedShieldCheck)
            ->emptyStateHeading('Belum pernah ada akses')
            ->emptyStateDescription('Tim KostPilot belum pernah masuk ke panel Anda.');
    }
}
