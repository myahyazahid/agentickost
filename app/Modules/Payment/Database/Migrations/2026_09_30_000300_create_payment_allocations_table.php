<?php

use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice is paid from a payment, the credit balance, or the deposit.
 * The deposit_transactions foreign key is added with that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('credit_transaction_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('deposit_transaction_id')->nullable();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('allocation_category', 16);
            $table->bigInteger('amount');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'payment_id']);
            $table->index(['tenant_id', 'credit_transaction_id']);
        });

        Check::enum('payment_allocations', 'allocation_category', AllocationCategory::class);
        Check::add('payment_allocations', 'amount_check', 'amount > 0');
        Check::add(
            'payment_allocations',
            'source_check',
            '(payment_id IS NOT NULL) + (credit_transaction_id IS NOT NULL) + (deposit_transaction_id IS NOT NULL) = 1',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
