<?php

use App\Support\Backup\BackupStore;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/*
 * Backups (NFR-BKP-01 to NFR-BKP-04). The MySQL tools are faked: each fake
 * writes what the real tool would, into the file the command asked for.
 */
beforeEach(function () {
    Storage::fake('backups');
    $this->travelTo('2026-10-05 19:00:00');
});

const COMPLETE_DUMP = "-- MySQL dump\nCREATE TABLE tenants (id char(26));\n-- Dump completed on 2026-10-05\n";

const CORE_TABLES = ['migrations', 'tenants', 'users', 'properties', 'contracts', 'invoices', 'payments', 'journal_entries', 'journal_lines'];

/**
 * @param  array<string, string>  $queries  output for SQL containing the key
 * @return ArrayObject<int, list<string>> the commands run, in order
 */
function fakeMysqlTools(string $dump = COMPLETE_DUMP, array $queries = []): ArrayObject
{
    $ran = new ArrayObject;

    Process::fake(function (PendingProcess $process) use ($dump, $queries, $ran) {
        /** @var list<string> $command */
        $command = $process->command;
        $ran->append($command);
        $argument = fn (string $prefix): string => substr((string) collect($command)->first(fn (string $part): bool => str_starts_with($part, $prefix)), strlen($prefix));

        expect($command[1])->toStartWith('--defaults-extra-file=')
            ->and(File::exists($argument('--defaults-extra-file=')))->toBeTrue()
            ->and(implode(' ', $command))->not->toContain('--password');

        if (str_contains($command[0], 'mysqldump')) {
            File::put($argument('--result-file='), $dump);
        }

        if (str_contains($command[0], 'mysqlbinlog')) {
            File::put($argument('--result-file=').end($command), 'binlog-bytes');
        }

        $sql = $argument('--execute=');

        foreach ($queries as $needle => $output) {
            if (str_contains($sql, $needle)) {
                return Process::result($output);
            }
        }

        return Process::result('');
    });

    return $ran;
}

/**
 * @return array<string, string>
 */
function restoredCopy(string $unbalanced): array
{
    return [
        'information_schema.tables' => implode("\n", CORE_TABLES),
        'SELECT (SELECT COUNT(*) FROM tenants)' => "2\t5\t40\t90",
        'HAVING SUM(debit_amount)' => $unbalanced,
    ];
}

it('dumps the database, stores it compressed on the backups disk, and prunes old dumps', function () {
    Storage::disk('backups')->put('database/2026/08/agentickost-20260820-190000.sql.gz', 'old');
    Storage::disk('backups')->put('database/2026/09/agentickost-20260920-190000.sql.gz', 'recent');
    $ran = fakeMysqlTools();

    $this->artisan('backup:database')->expectsOutputToContain('1 backup lama dihapus')->assertSuccessful();

    $path = 'database/2026/10/agentickost-20261005-190000.sql.gz';
    Storage::disk('backups')->assertExists($path);
    Storage::disk('backups')->assertMissing('database/2026/08/agentickost-20260820-190000.sql.gz');
    Storage::disk('backups')->assertExists('database/2026/09/agentickost-20260920-190000.sql.gz');

    expect(gzdecode((string) Storage::disk('backups')->get($path)))->toContain('-- Dump completed')
        ->and($ran[0])->toContain('--single-transaction', '--source-data=2')
        ->and(File::files(BackupStore::workDirectory()))->toBe([]);
});

it('refuses a dump cut short and uploads nothing', function () {
    fakeMysqlTools("-- MySQL dump\nCREATE TABLE tenants (id char(26));\n");

    $this->artisan('backup:database')->expectsOutputToContain('Dump tidak lengkap')->assertFailed();

    expect(Storage::disk('backups')->allFiles())->toBe([]);
});

it('copies every closed binary log once, leaving the one MySQL is writing', function () {
    fakeMysqlTools(queries: ['SHOW BINARY LOGS' => "binlog.000001\t1200\tNo\nbinlog.000002\t800\tNo\nbinlog.000003\t157\tNo\n"]);
    Storage::disk('backups')->put('binlog/binlog.000001.gz', 'already-copied');

    $this->artisan('backup:binlogs')->expectsOutputToContain('1 binary log disalin.')->assertSuccessful();

    Storage::disk('backups')->assertExists('binlog/binlog.000002.gz');
    Storage::disk('backups')->assertMissing('binlog/binlog.000003.gz');
    expect(gzdecode((string) Storage::disk('backups')->get('binlog/binlog.000002.gz')))->toBe('binlog-bytes');
});

it('restores the latest dump into a scratch database, checks it, and drops it', function () {
    config(['kostpilot.backup.restore_database' => 'kostpilot_restore_check']);
    Storage::disk('backups')->put('database/2026/10/agentickost-20261005-190000.sql.gz', (string) gzencode('-- dump'));
    $ran = fakeMysqlTools(queries: restoredCopy('0'));

    $this->artisan('backup:restore-test')->expectsOutputToContain('berhasil dipulihkan')->assertSuccessful();

    $lines = collect($ran->getArrayCopy())->map(fn (array $command): string => implode(' ', $command));
    expect($lines->first(fn (string $line): bool => str_contains($line, 'CREATE DATABASE')))->toContain('`kostpilot_restore_check`')
        ->and($lines->last())->toContain('DROP DATABASE IF EXISTS `kostpilot_restore_check`');
});

it('fails the restore test when the restored books do not balance', function () {
    config(['kostpilot.backup.restore_database' => 'kostpilot_restore_check']);
    Storage::disk('backups')->put('database/2026/10/agentickost-20261005-190000.sql.gz', (string) gzencode('-- dump'));
    fakeMysqlTools(queries: restoredCopy('3'));

    $this->artisan('backup:restore-test')->expectsOutputToContain('3 jurnal tidak seimbang')->assertFailed();
});

it('never restores over the application database', function () {
    config(['kostpilot.backup.restore_database' => config('database.connections.mysql.database')]);
    fakeMysqlTools();

    expect(fn () => $this->artisan('backup:restore-test')->run())->toThrow(RuntimeException::class, 'bukan database aplikasi');
    Process::assertNothingRan();
});
