<?php

use App\Modules\Billing\Enums\MeterUnit;
use App\Modules\Billing\Enums\UtilityKind;
use App\Modules\Billing\Enums\UtilityMode;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->string('utility', 16);
            $table->string('mode', 16);
            $table->string('unit', 8)->nullable();
            $table->bigInteger('rate_amount');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'utility', 'effective_from'], 'utility_rates_lookup_index');
        });

        Check::enum('utility_rates', 'utility', UtilityKind::class);
        Check::enum('utility_rates', 'mode', UtilityMode::class);
        Check::enum('utility_rates', 'unit', MeterUnit::class, nullable: true);
        Check::add('utility_rates', 'rate_amount_check', 'rate_amount >= 0');
        Check::add('utility_rates', 'range_check', 'effective_until IS NULL OR effective_until >= effective_from');
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_rates');
    }
};
