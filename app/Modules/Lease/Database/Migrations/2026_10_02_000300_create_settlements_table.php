<?php

use App\Modules\Lease\Enums\RoomAfterCheckOut;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deposit_transactions.settlement_id gets its foreign key here (schema §15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('inspection_id')->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->date('moved_out_on');
            $table->string('room_after', 16);
            $table->bigInteger('outstanding_amount')->default(0);
            $table->bigInteger('damage_amount')->default(0);
            $table->bigInteger('early_termination_amount')->default(0);
            $table->bigInteger('deposit_balance_amount')->default(0);
            $table->bigInteger('credit_balance_amount')->default(0);
            $table->bigInteger('result_amount')->default(0);
            $table->foreignUlid('final_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignUlid('refund_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_id']);
        });

        Check::add('settlements', 'status_check', "status IN ('draft', 'finalized')");
        Check::enum('settlements', 'room_after', RoomAfterCheckOut::class);
        Check::add(
            'settlements',
            'amounts_check',
            'outstanding_amount >= 0 AND damage_amount >= 0 AND early_termination_amount >= 0 AND deposit_balance_amount >= 0 AND credit_balance_amount >= 0',
        );

        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->foreign('settlement_id')->references('id')->on('settlements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposit_transactions', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
        });

        Schema::dropIfExists('settlements');
    }
};
