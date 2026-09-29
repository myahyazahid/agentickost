<?php

namespace App\Support\Console;

use App\Support\Subscriptions\ReadOnlyMode;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Runs the units of a scheduled command (a tenant, a contract, an invoice)
 * so that one failure does not stop the rest (NFR-OBS-01). Each failure is
 * reported to the exception handler, which sends it to Sentry in
 * production, and counted. The command then exits with a failure code, so
 * the schedule monitor raises an alert even though the other units ran.
 */
final class IsolatedRuns
{
    private int $failures = 0;

    /**
     * @param  Closure(): void  $work
     */
    public function attempt(Closure $work): void
    {
        try {
            $work();
        } catch (ReadOnlyMode) {
            // A read-only tenant is skipped on purpose (FR-SUB-04), not a failure.
        } catch (Throwable $exception) {
            report(self::reportable($exception));
            $this->failures++;
        }
    }

    public function failures(): int
    {
        return $this->failures;
    }

    /**
     * Prints the failure count and returns the command's exit code.
     */
    public function finish(Command $command): int
    {
        if ($this->failures === 0) {
            return Command::SUCCESS;
        }

        $command->error("{$this->failures} bagian gagal dan sudah dilaporkan. Bagian lain tetap diproses.");

        return Command::FAILURE;
    }

    /**
     * Laravel skips validation errors when reporting, since in a request the
     * user sees them. In a scheduled job nobody does, so they are wrapped in
     * an exception that is reported, with the messages and the original.
     */
    private static function reportable(Throwable $exception): Throwable
    {
        if (app(ExceptionHandler::class)->shouldReport($exception)) {
            return $exception;
        }

        $message = $exception instanceof ValidationException
            ? implode(' ', array_merge(...array_values($exception->errors())))
            : $exception->getMessage();

        return new RuntimeException('Pekerjaan terjadwal gagal: '.$message, previous: $exception);
    }
}
