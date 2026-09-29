<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Every job below reports to a Sentry cron monitor (NFR-OBS-01), which
 * alerts when a run fails or does not happen at all. Without a Sentry DSN
 * the monitors do nothing.
 */

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Hourly, so each property's own midnight (WIB, WITA, WIT) is picked up.
Schedule::command('contracts:activate-renewals')->hourly()->withoutOverlapping()->sentryMonitor();

// 01:00 UTC is 08:00 WIB.
Schedule::command('contracts:remind-ending')->dailyAt('01:00')->withoutOverlapping()->sentryMonitor();

// Hourly, so invoices and penalties follow each property's own date.
Schedule::command('billing:issue-invoices')->hourly()->withoutOverlapping()->sentryMonitor();
Schedule::command('billing:accrue-penalties')->hourly()->withoutOverlapping()->sentryMonitor();

// Backups (NFR-BKP-01 to NFR-BKP-04). 19:00 UTC is 02:00 WIB; the restore
// test runs on the 1st of each month, after that night's dump.
Schedule::command('backup:database')->dailyAt('19:00')->withoutOverlapping()->sentryMonitor();
Schedule::command('backup:binlogs')->hourlyAt(30)->withoutOverlapping()->sentryMonitor();
Schedule::command('backup:restore-test')->monthlyOn(1, '21:00')->withoutOverlapping()->sentryMonitor(maxRuntime: 240);
