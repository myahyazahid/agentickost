<?php

use App\Modules\Access\Enums\Role;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account verification for self-registered owners (FR-TNT-01) and staff
 * invitations by email (FR-USR-03). Accounts that exist already were made
 * by an operator, so they count as verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('phone');
        });

        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => DB::raw('created_at')]);

        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('role', 32);
            $table->json('property_ids');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
        });

        Check::add('staff_invitations', 'role_check', "role IN ('".implode("', '", array_map(fn (Role $role): string => $role->value, Role::staff()))."')");
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
