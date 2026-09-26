<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_holds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('contract_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->bigInteger('rent_amount');
            $table->text('reason')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'contract_id', 'start_date']);
        });

        Check::add('contract_holds', 'rent_amount_check', 'rent_amount >= 0');
        Check::add('contract_holds', 'range_check', 'end_date >= start_date');
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_holds');
    }
};
