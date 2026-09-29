<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super admin tools (FR-TNT-03 to FR-TNT-06): platform-wide settings such as
 * the trial length, the reason a tenant was frozen, and which owner account
 * an impersonation session used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 80)->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->text('frozen_reason')->nullable()->after('frozen_at');
        });

        Schema::table('impersonation_logs', function (Blueprint $table) {
            $table->foreignUlid('impersonated_user_id')->nullable()->after('tenant_id')->constrained('users')->restrictOnDelete();
            $table->index(['tenant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('impersonation_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'started_at']);
            $table->dropConstrainedForeignId('impersonated_user_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('frozen_reason');
        });

        Schema::dropIfExists('platform_settings');
    }
};
