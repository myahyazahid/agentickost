<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_accruals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->date('accrued_on');
            $table->bigInteger('amount');
            $table->json('rule_snapshot');
            $table->timestamp('waived_at')->nullable();
            $table->foreignUlid('waived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('waive_reason')->nullable();
            $table->timestamps();

            // The penalty job may run again: one accrual per invoice per day.
            $table->unique(['tenant_id', 'invoice_id', 'accrued_on']);
        });

        Check::add('penalty_accruals', 'amount_check', 'amount > 0');
        Check::add('penalty_accruals', 'waive_reason_check', 'waived_at IS NULL OR waive_reason IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_accruals');
    }
};
