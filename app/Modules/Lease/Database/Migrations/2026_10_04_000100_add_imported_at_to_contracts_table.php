<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks contracts that were already running when the tenant moved to
 * KostPilot (FR-ONB-02). Their deposit is carried in through the opening
 * balance, so billing never charges it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('imported_at')->nullable()->after('end_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('imported_at');
        });
    }
};
