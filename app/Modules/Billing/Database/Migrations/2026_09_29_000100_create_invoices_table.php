<?php

use App\Modules\Billing\Enums\InvoiceType;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice per contract period (docs/adr/0008-keputusan-mvp.md), so the
 * schema's resident_id column is not created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('payer_id')->constrained()->restrictOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('type', 16);
            $table->string('status', 16);
            $table->string('generation_key', 120)->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->bigInteger('items_total_amount')->default(0);
            $table->bigInteger('penalty_amount')->default(0);
            $table->bigInteger('paid_amount')->default(0);
            $table->bigInteger('credited_amount')->default(0);
            $table->bigInteger('balance_amount')->storedAs('items_total_amount + penalty_amount - paid_amount - credited_amount');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->string('issued_by_type', 32)->nullable();
            $table->ulid('issued_by_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'generation_key']);
            $table->index(['tenant_id', 'status', 'due_date']);
            $table->index(['tenant_id', 'contract_id', 'period_start']);
            $table->index(['tenant_id', 'payer_id', 'status']);
        });

        Check::enum('invoices', 'type', InvoiceType::class);
        Check::add('invoices', 'status_check', "status IN ('draft', 'issued', 'partial', 'paid', 'void')");
        Check::add('invoices', 'amounts_check', 'items_total_amount >= 0 AND penalty_amount >= 0 AND paid_amount >= 0 AND credited_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
