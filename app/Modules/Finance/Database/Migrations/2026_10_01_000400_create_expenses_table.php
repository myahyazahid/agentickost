<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * vendor_id and ticket_id stay plain columns until vendors (M2.2) and
 * tickets (M1.7) exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUlid('paid_from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->ulid('vendor_id')->nullable();
            $table->ulid('ticket_id')->nullable();
            $table->bigInteger('amount');
            $table->date('spent_on');
            $table->string('description', 255);
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'spent_on']);
            $table->index(['tenant_id', 'paid_from_account_id']);
        });

        Check::add('expenses', 'amount_check', 'amount > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
