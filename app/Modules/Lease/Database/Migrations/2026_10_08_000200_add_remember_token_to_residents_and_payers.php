<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps residents and payers logged in to the portal on their phone, so
 * they do not need a new code every visit (FR-PRT-01, FR-PRT-06).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->rememberToken()->after('anonymized_at');
        });

        Schema::table('payers', function (Blueprint $table) {
            $table->rememberToken()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropRememberToken();
        });

        Schema::table('payers', function (Blueprint $table) {
            $table->dropRememberToken();
        });
    }
};
