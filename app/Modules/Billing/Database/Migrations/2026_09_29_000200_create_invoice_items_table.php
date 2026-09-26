<?php

use App\Modules\Billing\Enums\InvoiceItemType;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('allocation_category', 16);
            $table->string('description');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->bigInteger('unit_amount');
            $table->bigInteger('amount');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->ulid('source_id')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'source_type', 'source_id']);
        });

        Check::enum('invoice_items', 'type', InvoiceItemType::class);
        Check::enum('invoice_items', 'allocation_category', AllocationCategory::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
