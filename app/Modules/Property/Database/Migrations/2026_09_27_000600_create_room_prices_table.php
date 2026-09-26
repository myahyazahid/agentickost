<?php

use App\Modules\Property\Enums\RentalPeriod;
use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUlid('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('rental_period', 16);
            $table->bigInteger('amount');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'room_type_id', 'rental_period', 'effective_from'], 'room_prices_type_lookup_index');
            $table->index(['tenant_id', 'room_id', 'rental_period', 'effective_from'], 'room_prices_room_lookup_index');
        });

        Check::enum('room_prices', 'rental_period', RentalPeriod::class);
        Check::add('room_prices', 'amount_check', 'amount >= 0');
        Check::add('room_prices', 'target_check', '(room_type_id IS NULL) <> (room_id IS NULL)');
        Check::add('room_prices', 'range_check', 'effective_until IS NULL OR effective_until >= effective_from');
    }

    public function down(): void
    {
        Schema::dropIfExists('room_prices');
    }
};
