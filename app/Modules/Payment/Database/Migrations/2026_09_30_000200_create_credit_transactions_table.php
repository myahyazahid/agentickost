<?php

use App\Modules\Payment\Enums\CreditTransactionType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->bigInteger('amount');
            $table->foreignUlid('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('booking_id')->nullable();
            $table->foreignUlid('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->string('created_by_type', 32);
            $table->ulid('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id', 'occurred_on']);
            $table->index(['tenant_id', 'payment_id']);
        });

        Check::enum('credit_transactions', 'type', CreditTransactionType::class);
        Check::add('credit_transactions', 'amount_check', 'amount <> 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
