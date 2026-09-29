<?php

namespace App\Support\Backup;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Runs the MySQL command-line tools (mysqldump, mysql, mysqlbinlog) with
 * the backup account. The password goes into a short-lived option file
 * readable only by the app, never onto the command line where other users
 * of the server could see it.
 */
final class MysqlClient
{
    /**
     * @param  array{host: string, port: string, username: string, password: string, database: string}  $connection
     * @param  array{mysqldump: string, mysql: string, mysqlbinlog: string}  $binaries
     */
    public function __construct(
        private readonly array $connection,
        private readonly array $binaries,
    ) {}

    public static function fromConfig(): self
    {
        $default = config('database.default');

        return new self(
            [
                'host' => (string) config("database.connections.{$default}.host"),
                'port' => (string) config("database.connections.{$default}.port"),
                'username' => (string) config('kostpilot.backup.username'),
                'password' => (string) config('kostpilot.backup.password'),
                'database' => (string) config("database.connections.{$default}.database"),
            ],
            [
                'mysqldump' => (string) config('kostpilot.backup.mysqldump'),
                'mysql' => (string) config('kostpilot.backup.mysql'),
                'mysqlbinlog' => (string) config('kostpilot.backup.mysqlbinlog'),
            ],
        );
    }

    public function database(): string
    {
        return $this->connection['database'];
    }

    /**
     * @param  'mysqldump'|'mysql'|'mysqlbinlog'  $tool
     * @param  list<string>  $arguments
     */
    public function run(string $tool, array $arguments, int $timeout = 3600): ProcessResult
    {
        $optionFile = $this->writeOptionFile();

        try {
            return Process::timeout($timeout)
                ->run([$this->binaries[$tool], "--defaults-extra-file={$optionFile}", ...$arguments])
                ->throw();
        } finally {
            File::delete($optionFile);
        }
    }

    /**
     * Runs SQL with the mysql client and returns the rows, tab-separated,
     * without column names.
     *
     * @return list<list<string>>
     */
    public function query(string $sql, ?string $database = null): array
    {
        $output = $this->run('mysql', [
            '--batch',
            '--skip-column-names',
            ...($database !== null ? ["--database={$database}"] : []),
            "--execute={$sql}",
        ])->output();

        return array_values(array_map(
            fn (string $line): array => explode("\t", $line),
            array_filter(preg_split('/\R/', trim($output)) ?: [], fn (string $line): bool => $line !== ''),
        ));
    }

    private function writeOptionFile(): string
    {
        $path = BackupStore::workDirectory().DIRECTORY_SEPARATOR.Str::random(24).'.cnf';

        File::put($path, implode("\n", [
            '[client]',
            'host='.$this->connection['host'],
            'port='.$this->connection['port'],
            'user='.$this->connection['username'],
            'password="'.addcslashes($this->connection['password'], '"\\').'"',
            '',
        ]));
        File::chmod($path, 0600);

        return $path;
    }
}
