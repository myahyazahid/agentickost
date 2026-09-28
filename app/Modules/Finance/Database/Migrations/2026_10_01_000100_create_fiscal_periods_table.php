<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods are created as journals land in them. Closing them is P1 (M1.5.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 16);
            $table->foreignUlid('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('reopened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'year', 'month']);
        });

        Check::add('fiscal_periods', 'status_check', "status IN ('open', 'closed')");
        Check::add('fiscal_periods', 'month_check', 'month BETWEEN 1 AND 12');
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
    }
};
