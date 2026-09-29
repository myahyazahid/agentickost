<?php

namespace App\Modules\Documents\Filament;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Tenancy\Support\TenantStorage;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;

/**
 * File upload that lands in the tenant's directory on the default disk. The
 * resulting paths are passed to an Action, which records them with
 * AttachmentSync.
 *
 * The field only accepts the collection's file types, and a path typed into
 * the form state instead of uploaded is refused unless it is already a file
 * of the record being edited, or of another record the user may view.
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
            ->maxSize(5120)
            ->acceptedFileTypes($collection->acceptedMimeTypes())
            ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?Model $record): bool => self::mayReuse($file, $collection, $record));
    }

    /**
     * An existing path is fine when it is an attachment of the record in the
     * form, or, without a record, of something the user may view.
     */
    public static function mayReuse(string $path, AttachmentCollection $collection, ?Model $record): bool
    {
        $attachment = Attachment::query()
            ->where('path', $path)
            ->where('collection', $collection->value)
            ->first();

        if ($attachment === null) {
            return false;
        }

        if ($record !== null) {
            return $attachment->attachable_type === $record->getMorphClass() && $attachment->attachable_id === $record->getKey();
        }

        $owner = $attachment->attachable()->first();

        return $owner !== null && User::current()->can('view', $owner);
    }
}
