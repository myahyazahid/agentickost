<?php

use App\Modules\Payment\Enums\PaymentChannel;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * booking_id stays a plain column until bookings exist (M2.1, schema §15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('payer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->ulid('booking_id')->nullable();
            $table->string('receipt_number', 40)->nullable();
            $table->string('method', 16);
            $table->string('channel', 16);
            $table->string('status', 16);
            $table->bigInteger('amount');
            $table->timestamp('paid_at');
            $table->foreignUlid('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('received_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('verified_by_type', 32)->nullable();
            $table->ulid('verified_by_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'receipt_number']);
            $table->unique(['tenant_id', 'gateway_transaction_id']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'payer_id']);
            $table->index(['tenant_id', 'contract_id']);
            $table->index(['tenant_id', 'received_by_user_id', 'method', 'status']);
        });

        Check::enum('payments', 'method', PaymentMethod::class);
        Check::enum('payments', 'channel', PaymentChannel::class);
        Check::add('payments', 'status_check', "status IN ('pending', 'verified', 'rejected', 'reversed')");
        Check::add('payments', 'amount_check', 'amount > 0');
        Check::add('payments', 'cash_receiver_check', "method <> 'cash' OR received_by_user_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
