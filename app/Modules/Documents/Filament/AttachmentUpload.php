<?php

namespace App\Modules\Documents\Filament;

use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Tenancy\Support\TenantStorage;
use Filament\Forms\Components\FileUpload;

/**
 * File upload that lands in the tenant's directory on the default disk. The
 * resulting paths are passed to an Action, which records them with
 * AttachmentSync.
 */
final class AttachmentUpload
{
    public static function make(string $name, AttachmentCollection $collection): FileUpload
    {
        return FileUpload::make($name)
            ->disk(fn (): string => (string) config('filesystems.default'))
            ->directory(fn (): string => app(TenantStorage::class)->path($collection->directory()))
            ->visibility('private')
            ->multiple()
            ->maxSize(5120);
    }
}
