<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Hourly, so each property's own midnight (WIB, WITA, WIT) is picked up.
Schedule::command('contracts:activate-renewals')->hourly()->withoutOverlapping();

// 01:00 UTC is 08:00 WIB.
Schedule::command('contracts:remind-ending')->dailyAt('01:00')->withoutOverlapping();

// Hourly, so invoices and penalties follow each property's own date.
Schedule::command('billing:issue-invoices')->hourly()->withoutOverlapping();
Schedule::command('billing:accrue-penalties')->hourly()->withoutOverlapping();
