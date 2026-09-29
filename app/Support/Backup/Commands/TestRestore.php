<?php

namespace App\Support\Backup\Commands;

use App\Support\Backup\BackupStore;
use App\Support\Backup\MysqlClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * The monthly restore test (NFR-BKP-04): loads the latest dump into a
 * scratch database, checks that the core tables are there and that every
 * journal balances, then drops the scratch database. A backup that cannot
 * be restored fails the command, and the schedule monitor alerts.
 */
final class TestRestore extends Command
{
    /**
     * Tables a restore must bring back for the app to run.
     */
    private const CORE_TABLES = ['migrations', 'tenants', 'users', 'properties', 'contracts', 'invoices', 'payments', 'journal_entries', 'journal_lines'];

    protected $signature = 'backup:restore-test';

    protected $description = 'Uji pulihkan backup terakhir ke database sementara';

    public function handle(MysqlClient $mysql, BackupStore $store): int
    {
        $scratch = (string) config('agentickost.backup.restore_database');

        if (preg_match('/^\w+$/', $scratch) !== 1 || $scratch === $mysql->database()) {
            throw new RuntimeException('BACKUP_RESTORE_DATABASE harus nama database lain (huruf, angka, garis bawah), bukan database aplikasi.');
        }

        $dump = $store->latestDump();

        if ($dump === null) {
            $this->error('Belum ada backup di disk backup.');

            return self::FAILURE;
        }

        $local = $store->download($dump);
        $started = microtime(true);

        try {
            $mysql->query("DROP DATABASE IF EXISTS `{$scratch}`; CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            // Without binary logging for this session: the import would
            // otherwise be logged again and copied into the binlog backups.
            $mysql->run('mysql', ['--init-command=SET SESSION sql_log_bin = 0', "--database={$scratch}", "--execute=source {$local}"], timeout: 4 * 3600);

            $problems = $this->check($mysql, $scratch);
        } finally {
            File::delete($local);
            $mysql->query("DROP DATABASE IF EXISTS `{$scratch}`;");
        }

        $minutes = round((microtime(true) - $started) / 60, 1);

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $this->components->info("Backup {$dump} berhasil dipulihkan dalam {$minutes} menit.");

        return self::SUCCESS;
    }

    /**
     * @return list<string> what is wrong with the restored copy
     */
    private function check(MysqlClient $mysql, string $scratch): array
    {
        $tables = array_column($mysql->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '{$scratch}';"), 0);
        $missing = array_values(array_diff(self::CORE_TABLES, $tables));

        if ($missing !== []) {
            return ['Tabel tidak ada di hasil pemulihan: '.implode(', ', $missing).'.'];
        }

        $counts = $mysql->query('SELECT (SELECT COUNT(*) FROM tenants), (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM invoices), (SELECT COUNT(*) FROM journal_entries);', $scratch)[0] ?? [];
        $this->components->twoColumnDetail('Tabel', (string) count($tables));
        $this->components->twoColumnDetail('Tenant, pengguna, tagihan, jurnal', implode(', ', $counts));

        $unbalanced = (int) ($mysql->query('SELECT COUNT(*) FROM (SELECT journal_entry_id FROM journal_lines GROUP BY journal_entry_id HAVING SUM(debit_amount) <> SUM(credit_amount)) AS unbalanced;', $scratch)[0][0] ?? 0);

        return $unbalanced > 0 ? ["{$unbalanced} jurnal tidak seimbang di hasil pemulihan."] : [];
    }
}
