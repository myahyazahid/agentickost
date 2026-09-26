<?php

use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('number', 40);
            $table->string('allocation_category', 16);
            $table->bigInteger('amount');
            $table->text('reason');
            $table->date('issued_on');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'invoice_id']);
        });

        Check::enum('credit_notes', 'allocation_category', AllocationCategory::class);
        Check::add('credit_notes', 'amount_check', 'amount > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
