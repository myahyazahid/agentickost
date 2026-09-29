<?php

namespace App\Support\Backup\Commands;

use App\Support\Backup\BackupStore;
use App\Support\Backup\MysqlClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies MySQL's binary logs to the backups disk every hour, so the
 * database can be rebuilt up to about an hour before a failure
 * (NFR-BKP-01, RPO in NFR-BKP-02). The current log is closed first; every
 * closed log not yet copied is fetched through the server connection, so
 * the app needs no access to MySQL's data directory.
 */
final class BackupBinaryLogs extends Command
{
    protected $signature = 'backup:binlogs';

    protected $description = 'Salin binary log MySQL ke disk backup untuk pemulihan ke titik waktu tertentu';

    public function handle(MysqlClient $mysql, BackupStore $store): int
    {
        if (! config('agentickost.backup.binary_logs')) {
            $this->components->warn('Backup binary log dimatikan (BACKUP_BINARY_LOGS=false).');

            return self::SUCCESS;
        }

        $rows = $mysql->query('FLUSH BINARY LOGS; SHOW BINARY LOGS;');
        $names = array_values(array_filter(array_column($rows, 0), fn (string $name): bool => $name !== ''));

        // The last log is the one MySQL writes to now.
        array_pop($names);

        $copied = 0;

        foreach ($names as $name) {
            if ($store->hasBinaryLog($name)) {
                continue;
            }

            $local = BackupStore::workDirectory().DIRECTORY_SEPARATOR.$name;

            try {
                $mysql->run('mysqlbinlog', [
                    '--read-from-remote-server',
                    '--raw',
                    '--result-file='.BackupStore::workDirectory().DIRECTORY_SEPARATOR,
                    $name,
                ]);

                $store->upload($local, BackupStore::BINARY_LOGS."/{$name}.gz");
                $copied++;
            } finally {
                File::delete($local);
            }
        }

        $this->components->info("{$copied} binary log disalin.");

        return self::SUCCESS;
    }
}
