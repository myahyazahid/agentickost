<?php

use App\Modules\Billing\Enums\UtilityKind;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_id')->constrained()->restrictOnDelete();
            $table->string('utility', 16);
            $table->date('reading_date');
            $table->decimal('previous_value', 12, 2);
            $table->decimal('current_value', 12, 2);
            $table->decimal('usage', 12, 2)->storedAs('current_value - previous_value');
            $table->boolean('is_meter_replaced')->default(false);
            $table->bigInteger('rate_amount');
            $table->bigInteger('amount');
            $table->foreignUlid('invoice_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('client_uuid', 36)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'room_id', 'utility', 'reading_date']);
            $table->unique(['tenant_id', 'client_uuid']);
        });

        Check::enum('meter_readings', 'utility', UtilityKind::class);
        Check::add('meter_readings', 'value_check', 'is_meter_replaced OR current_value >= previous_value');
        Check::add('meter_readings', 'amount_check', 'amount >= 0 AND rate_amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
