<?php

use App\Support\Database\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('property_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 20);
            $table->string('floor', 10)->nullable();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->string('status', 32);
            $table->json('facilities')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'property_id', 'number']);
            $table->index(['tenant_id', 'property_id', 'status']);
        });

        Check::add('rooms', 'status_check', "status IN ('available', 'held', 'booked', 'occupied', 'vacating', 'maintenance')");
        Check::add('rooms', 'capacity_check', 'capacity >= 1');
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
