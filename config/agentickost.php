<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity Hash Key
    |--------------------------------------------------------------------------
    |
    | Key for the HMAC of residents' identity numbers (schema §1.9). The
    | numbers themselves are encrypted with APP_KEY; this separate key lets
    | duplicates be found without decrypting. Generate with:
    | php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    |
    */

    'identity_hash_key' => env('IDENTITY_HASH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Trial Length
    |--------------------------------------------------------------------------
    |
    | Days of trial a newly registered tenant gets (FR-TNT-03) until a super
    | admin sets another length in the admin panel.
    |
    */

    'trial_days' => (int) env('AGENTICKOST_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Required Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Owners, accountants (FR-USR-05), and super admins (NFR-SEC-05) must set
    | up an authenticator app before using their panel. Only the test suite
    | turns this off, so tests can open pages without a second factor; the
    | tests for two-factor itself turn it back on.
    |
    */

    'two_factor_required' => (bool) env('AGENTICKOST_TWO_FACTOR_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Backups
    |--------------------------------------------------------------------------
    |
    | Daily dumps and hourly binary logs go to the `backups` disk
    | (NFR-BKP-01 to NFR-BKP-03); a monthly restore test proves they work
    | (NFR-BKP-04). The backup account needs more rights than the app user,
    | see docs/deployment.md. Client paths are for servers where the MySQL
    | tools are not on PATH.
    |
    */

    'backup' => [
        'disk' => env('BACKUP_DISK', 'backups'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 35),
        'username' => env('BACKUP_DB_USERNAME', env('DB_USERNAME')),
        'password' => env('BACKUP_DB_PASSWORD', env('DB_PASSWORD')),
        'binary_logs' => (bool) env('BACKUP_BINARY_LOGS', true),
        'restore_database' => env('BACKUP_RESTORE_DATABASE', env('DB_DATABASE', 'agentickost').'_restore_test'),
        'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', 'mysql'),
        'mysqlbinlog' => env('BACKUP_MYSQLBINLOG', 'mysqlbinlog'),
    ],

];
