<?php

namespace App\Modules\Subscription\Jobs;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Models\Attachment;
use App\Modules\Documents\Support\AttachmentSync;
use App\Modules\Tenancy\Concerns\BelongsToTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantStorage;
use App\Modules\Tenancy\TenantContext;
use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Packs all of the current tenant's data into a zip (FR-SUB-06): one CSV per
 * table and every attachment, decrypted. The requester gets a download link
 * in their notifications, valid for DOWNLOAD_DAYS; older exports of the
 * tenant are removed.
 *
 * Columns a model hides (passwords, tokens, identity numbers) are left out.
 */
final class ExportTenantData implements ShouldQueue
{
    use Queueable;

    public const DOWNLOAD_DAYS = 7;

    public int $timeout = 1800;

    public function __construct(public readonly string $userId) {}

    public function handle(TenantContext $tenants, TenantStorage $storage, AttachmentSync $attachments): void
    {
        $tenant = $tenants->tenant();
        $workdir = storage_path('app/private/exports/'.Str::ulid());
        File::ensureDirectoryExists($workdir);

        try {
            $zipPath = $workdir.'/export.zip';
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat arsip ekspor.');
            }

            $this->writeCsv($zip, $workdir, 'usaha.csv', [Tenant::query()->whereKey($tenant->id)->firstOrFail()]);

            foreach ($this->tenantModels() as $alias => $class) {
                $records = $class::query()
                    ->withoutGlobalScopes()
                    ->where((new $class)->qualifyColumn('tenant_id'), $tenant->id)
                    ->orderBy((new $class)->getQualifiedKeyName())
                    ->lazyById(500);

                $this->writeCsv($zip, $workdir, "{$alias}.csv", $records);
            }

            $this->addAttachments($zip, $attachments, $tenant->id);
            $zip->close();

            $this->pruneOldExports($storage);

            $name = 'data-'.now($tenant->default_timezone)->format('Ymd-His').'-'.Str::lower((string) Str::ulid()).'.zip';
            $stream = fopen($zipPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Gagal membaca arsip ekspor.');
            }

            Storage::writeStream($storage->path("exports/{$name}"), $stream);
            fclose($stream);

            $this->notify($name);
        } finally {
            File::deleteDirectory($workdir);
        }
    }

    /**
     * Every tenant-owned model, by its morph alias.
     *
     * @return array<string, class-string<Model>>
     */
    private function tenantModels(): array
    {
        $models = array_filter(
            Relation::morphMap(),
            fn (string $class): bool => in_array(BelongsToTenant::class, class_uses_recursive($class), true),
        );
        ksort($models);

        return $models;
    }

    /**
     * @param  iterable<Model>  $records
     */
    private function writeCsv(ZipArchive $zip, string $workdir, string $name, iterable $records): void
    {
        $path = $workdir.'/'.$name;
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Gagal menulis {$name}.");
        }

        // Excel reads a UTF-8 file correctly only with the byte order mark.
        fwrite($handle, "\xEF\xBB\xBF");
        $header = null;

        foreach ($records as $record) {
            $row = $record->attributesToArray();

            if ($header === null) {
                $header = array_keys($row);
                fputcsv($handle, $header, escape: '');
            }

            fputcsv($handle, array_map(fn (string $column): string => $this->cell($row[$column] ?? null), $header), escape: '');
        }

        fclose($handle);

        if ($header !== null) {
            $zip->addFile($path, $name);
        }
    }

    private function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => (string) $value,
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        };

        // A cell starting with these runs as a formula when the file is
        // opened in a spreadsheet; names typed by residents must not.
        if ($text !== '' && ! is_numeric($text) && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$text;
        }

        return $text;
    }

    private function addAttachments(ZipArchive $zip, AttachmentSync $attachments, string $tenantId): void
    {
        $query = Attachment::query()->withoutGlobalScopes()->where('tenant_id', $tenantId);

        foreach ($query->lazyById(100) as $attachment) {
            try {
                $name = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', Str::ascii($attachment->original_name));
                $zip->addFromString(
                    "lampiran/{$attachment->attachable_type}/{$attachment->attachable_id}/{$attachment->id}-{$name}",
                    $attachments->contents($attachment),
                );
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    private function pruneOldExports(TenantStorage $storage): void
    {
        foreach (Storage::files($storage->path('exports')) as $file) {
            Storage::delete($file);
        }
    }

    private function notify(string $name): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $expires = now()->addDays(self::DOWNLOAD_DAYS);

        Notification::make()
            ->success()
            ->title('Ekspor data siap diunduh')
            ->body('Tautan berlaku '.self::DOWNLOAD_DAYS.' hari. Arsip berisi data pribadi penghuni; simpan di tempat yang aman.')
            ->actions([
                Action::make('download')
                    ->label('Unduh')
                    ->url(URL::temporarySignedRoute('subscription.export.download', $expires, ['file' => $name])),
            ])
            ->sendToDatabase($user);
    }
}
