<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recovery codes for super admins' two-factor authentication (NFR-SEC-05),
 * encrypted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $table->dropColumn('two_factor_recovery_codes');
        });
    }
};
