<?php

use App\Modules\Finance\Enums\DepositTransactionType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * settlement_id stays a plain column until settlements exist (M1.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->bigInteger('amount');
            $table->text('reason')->nullable();
            $table->foreignUlid('payment_allocation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('related_contract_id')->nullable()->constrained('contracts')->restrictOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('settlement_id')->nullable();
            $table->date('occurred_on');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id', 'occurred_on']);
        });

        Check::enum('deposit_transactions', 'type', DepositTransactionType::class);
        Check::add('deposit_transactions', 'amount_check', 'amount <> 0');
        Check::add('deposit_transactions', 'reason_check', "type <> 'deducted' OR reason IS NOT NULL");

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreign('deposit_transaction_id')->references('id')->on('deposit_transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['deposit_transaction_id']);
        });

        Schema::dropIfExists('deposit_transactions');
    }
};
