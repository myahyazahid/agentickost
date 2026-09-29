<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Where backups live (NFR-BKP-03): the `backups` disk, kept apart from the
 * app server and its file storage. Daily dumps sit under database/, binary
 * logs under binlog/. Files are compressed before upload.
 */
final class BackupStore
{
    public const DUMPS = 'database';

    public const BINARY_LOGS = 'binlog';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('kostpilot.backup.disk'));
    }

    /**
     * Local scratch space for dumps and option files; emptied as each
     * command finishes.
     */
    public static function workDirectory(): string
    {
        $directory = storage_path('app/private/backup-work');
        File::ensureDirectoryExists($directory, 0700);

        return $directory;
    }

    public static function dumpPath(CarbonImmutable $takenAt): string
    {
        return self::DUMPS.'/'.$takenAt->format('Y/m').'/agentickost-'.$takenAt->format('Ymd-His').'.sql.gz';
    }

    public function latestDump(): ?string
    {
        $dumps = array_filter($this->disk()->allFiles(self::DUMPS), fn (string $path): bool => str_ends_with($path, '.sql.gz'));
        sort($dumps);

        return $dumps === [] ? null : end($dumps);
    }

    public function hasBinaryLog(string $name): bool
    {
        return $this->disk()->exists(self::BINARY_LOGS."/{$name}.gz");
    }

    /**
     * Compresses a local file, uploads it, and removes the local copies.
     */
    public function upload(string $localPath, string $target): void
    {
        $compressed = self::gzip($localPath);
        $stream = fopen($compressed, 'rb') ?: throw new RuntimeException("Tidak bisa membaca {$compressed}.");

        try {
            $this->disk()->writeStream($target, $stream);
        } finally {
            fclose($stream);
            File::delete([$localPath, $compressed]);
        }
    }

    /**
     * Downloads and decompresses a backup into the work directory.
     */
    public function download(string $path): string
    {
        $local = self::workDirectory().DIRECTORY_SEPARATOR.basename($path, '.gz');
        $source = $this->disk()->readStream($path) ?? throw new RuntimeException("Backup {$path} tidak bisa dibaca.");
        $target = fopen($local, 'wb') ?: throw new RuntimeException("Tidak bisa menulis {$local}.");
        $inflate = inflate_init(ZLIB_ENCODING_GZIP) ?: throw new RuntimeException('zlib tidak tersedia.');

        try {
            while (! feof($source)) {
                fwrite($target, (string) inflate_add($inflate, (string) fread($source, 1 << 20)));
            }

            fwrite($target, (string) inflate_add($inflate, '', ZLIB_FINISH));
        } finally {
            fclose($source);
            fclose($target);
        }

        return $local;
    }

    /**
     * Deletes backups older than the retention period, judged by the date in
     * the file name for dumps and by the upload time for binary logs.
     */
    public function prune(int $keepDays, CarbonImmutable $now): int
    {
        $cutoff = $now->subDays($keepDays);
        $deleted = 0;

        foreach ($this->disk()->allFiles(self::DUMPS) as $path) {
            if (preg_match('/-(\d{8})-\d{6}\.sql\.gz$/', $path, $match) === 1
                && CarbonImmutable::createFromFormat('Ymd', $match[1])?->startOfDay()->lessThan($cutoff)) {
                $this->disk()->delete($path);
                $deleted++;
            }
        }

        foreach ($this->disk()->allFiles(self::BINARY_LOGS) as $path) {
            if ($this->disk()->lastModified($path) < $cutoff->getTimestamp()) {
                $this->disk()->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function gzip(string $path): string
    {
        $compressed = "{$path}.gz";
        $source = fopen($path, 'rb') ?: throw new RuntimeException("Tidak bisa membaca {$path}.");
        $target = gzopen($compressed, 'wb6') ?: throw new RuntimeException("Tidak bisa menulis {$compressed}.");

        try {
            while (! feof($source)) {
                gzwrite($target, (string) fread($source, 1 << 20));
            }
        } finally {
            fclose($source);
            gzclose($target);
        }

        return $compressed;
    }
}
