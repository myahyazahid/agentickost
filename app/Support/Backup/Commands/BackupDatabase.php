<?php

namespace App\Support\Backup\Commands;

use App\Support\Backup\BackupStore;
use App\Support\Backup\MysqlClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;

/**
 * Daily full dump of the database to the backups disk (NFR-BKP-01). The
 * dump is one consistent snapshot and, with binary logs on, records the
 * binary log position it was taken at, which is where point-in-time
 * recovery starts replaying. Old dumps are pruned afterwards.
 */
final class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Backup penuh database ke disk backup';

    public function handle(MysqlClient $mysql, BackupStore $store): int
    {
        $takenAt = CarbonImmutable::now('UTC');
        $local = BackupStore::workDirectory().DIRECTORY_SEPARATOR.basename(BackupStore::dumpPath($takenAt), '.gz');

        try {
            $mysql->run('mysqldump', [
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--set-gtid-purged=OFF',
                ...(config('agentickost.backup.binary_logs') ? ['--source-data=2'] : []),
                "--result-file={$local}",
                $mysql->database(),
            ]);

            if (! self::isComplete($local)) {
                $this->error('Dump tidak lengkap: baris penutup mysqldump tidak ditemukan.');

                return self::FAILURE;
            }

            $size = (int) File::size($local);
            $store->upload($local, BackupStore::dumpPath($takenAt));
        } finally {
            File::delete($local);
        }

        $pruned = $store->prune((int) config('agentickost.backup.keep_days'), $takenAt);

        $this->components->info('Backup '.BackupStore::dumpPath($takenAt).' tersimpan ('.Number::fileSize($size).' sebelum dikompres). '.$pruned.' backup lama dihapus.');

        return self::SUCCESS;
    }

    /**
     * mysqldump ends a finished dump with a "Dump completed" comment; a dump
     * cut short by an error or a full disk does not have it.
     */
    private static function isComplete(string $path): bool
    {
        if (! File::exists($path) || File::size($path) === 0) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        fseek($handle, -min(512, (int) File::size($path)), SEEK_END);
        $tail = (string) fread($handle, 512);
        fclose($handle);

        return str_contains($tail, '-- Dump completed');
    }
}
