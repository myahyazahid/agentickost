<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lines are written once with their entry and never changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('account_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->bigInteger('debit_amount')->default(0);
            $table->bigInteger('credit_amount')->default(0);
            $table->string('memo', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'account_id', 'journal_entry_id']);
            $table->index(['tenant_id', 'contract_id']);
        });

        Check::add('journal_lines', 'amounts_check', 'debit_amount >= 0 AND credit_amount >= 0');
        Check::add('journal_lines', 'one_side_check', '(debit_amount = 0) <> (credit_amount = 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
