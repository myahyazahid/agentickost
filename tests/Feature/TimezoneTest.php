<?php

use Illuminate\Support\Facades\DB;

it('runs the application clock in UTC', function () {
    expect(now()->getTimezone()->getName())->toBe('UTC');
});

it('runs the database session in UTC', function () {
    expect(DB::scalar('select @@session.time_zone'))->toBe('+00:00');
});
